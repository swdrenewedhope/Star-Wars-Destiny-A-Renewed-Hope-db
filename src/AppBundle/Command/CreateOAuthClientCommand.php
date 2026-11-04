<?php
namespace AppBundle\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateOAuthClientCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:oauth-server:client:create')
            ->setDescription('Creates a new OAuth client')
            ->addOption(
                'grant-type', 't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Grant type for client. Can use multiple: authorization_code, refresh_token, token, password, client_credentials',
                ["authorization_code", "refresh_token"]
            )
            ->addArgument('redirect-uri', InputArgument::REQUIRED, 'Sets redirect URI for client')
            ->addArgument('client-name', InputArgument::REQUIRED,'Display name of the client');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$redirectUris = [ $input->getArgument('redirect-uri') ];
        $clientManager = $this->getContainer()->get('fos_oauth_server.client_manager.default');
        $client = $clientManager->createClient();
        $client->setRedirectUris($redirectUris);
        $client->setAllowedGrantTypes($input->getOption('grant-type'));
        $client->setName($input->getArgument('client-name'));
        $clientManager->updateClient($client);
        $output->writeln( sprintf(
                "<comment>Added new client. See credentials below.</comment>\nID: <info>%s</info>\nSecret: <info>%s</info>",
                $client->getPublicId(),
                $client->getSecret()
            ));}}