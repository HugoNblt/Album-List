<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée un compte utilisateur (bêta privée, inscriptions publiques fermées).
 * Sans --password, un mot de passe aléatoire est généré et affiché une seule fois.
 */
#[AsCommand(name: 'app:user:create', description: 'Crée un compte utilisateur (pseudo + e-mail), mot de passe généré si absent')]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Pseudo (3 à 20 caractères : A-Z a-z 0-9 _ . -)')
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail, utilisé pour se connecter')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (8 caractères minimum) ; généré si absent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $password */
        $password = $input->getOption('password');
        $generated = $password === null;
        $password ??= self::generatePassword();

        if (mb_strlen($password) < 8) {
            $io->error('Le mot de passe doit faire au moins 8 caractères.');

            return Command::INVALID;
        }

        $user = new User();
        $user->setUsername((string) $input->getArgument('username'));
        $user->setEmail((string) $input->getArgument('email'));

        // Mêmes règles qu'à l'inscription : format du pseudo, e-mail valide, unicité
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath() . ' : ' . $error->getMessage());
            }

            return Command::INVALID;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Compte « %s » créé (connexion avec %s).', $user->getUsername(), $user->getEmail()));
        if ($generated) {
            $io->warning('Mot de passe généré, affiché une seule fois : note-le avant de fermer le terminal.');
            $io->writeln('Mot de passe : ' . $password);
            $io->newLine();
        }

        return Command::SUCCESS;
    }

    /** 16 caractères aléatoires sans caractères ambigus (0/O, 1/l/I). */
    private static function generatePassword(): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_!?';
        $password = '';
        for ($i = 0; $i < 16; ++$i) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
