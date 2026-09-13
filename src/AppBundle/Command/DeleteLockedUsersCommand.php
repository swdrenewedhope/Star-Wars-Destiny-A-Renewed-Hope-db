<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class DeleteLockedUsersCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
        ->setName('app:delete-unverified')
        ->setDescription('Delete users who have not confirmed email after signing >week.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $limit = new \DateTime();
        $limit->sub(new \DateInterval('P7D'));
        $count = 0;

        $users = $em->getRepository('AppBundle:User')->findBy(array('enabled' => false));

        foreach($users as $user) {
            if($user->getDateCreation() < $limit) { $count++;
				$collection = $em->getRepository('AppBundle:Collection')->findOneBy(array('user' => $user));
				$em->remove($collection);
				$em->remove($user);
            }
        }
        $em->flush();
        $output->writeln(date('c') . " Deleted $count garbage accounts.");
    }
}
