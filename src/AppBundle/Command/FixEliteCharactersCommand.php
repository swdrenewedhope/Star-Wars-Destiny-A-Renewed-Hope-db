<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class FixEliteCharactersCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:decks:fix-elite-characters')
            ->setDescription('Store imported elite characters as one copy with two dice instead of two copies')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only report the slots that would be fixed.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $validator = $this->getContainer()->get('deck_validation_helper');
        $dryRun = $input->getOption('dry-run');

        $decks = [];
        foreach($this->findSlots('Deckslot') as $slot)
        {
            $deck = $slot->getDeck();
            $output->writeln(sprintf('deck %d: %s %dx/%d dice', $deck->getId(), $slot->getCard()->getCode(), $slot->getQuantity(), $slot->getDice()));
            $this->fixSlot($slot);
            $decks[$deck->getId()] = $deck;
        }

        $decklists = [];
        foreach($this->findSlots('Decklistslot') as $slot)
        {
            $decklist = $slot->getDecklist();
            $output->writeln(sprintf('decklist %d: %s %dx/%d dice', $decklist->getId(), $slot->getCard()->getCode(), $slot->getQuantity(), $slot->getDice()));
            $this->fixSlot($slot);
            $decklists[$decklist->getId()] = $decklist;
        }

        foreach($decks as $deck)
        {
            $deck->setProblem($validator->findProblem($deck));
        }
        foreach($decklists as $decklist)
        {
            $decklist->setSignature(md5(json_encode($decklist->getSlots()->getContent())));
        }

        if($dryRun)
        {
            $output->writeln(date('c') . sprintf(' Dry run: %d decks and %d decklists would be fixed.', count($decks), count($decklists)));
            return;
        }

        $em->flush();
        $output->writeln(date('c') . sprintf(' %d decks and %d decklists fixed.', count($decks), count($decklists)));
    }

    private function findSlots($entity)
    {
        return $this->getContainer()->get('doctrine')->getManager()
            ->createQuery("SELECT s FROM AppBundle:$entity s JOIN s.card c JOIN c.type t WHERE c.isUnique = :yes AND c.hasDie = :yes AND t.code = :type AND s.quantity > :one")
            ->setParameters(['yes' => true, 'type' => 'character', 'one' => 1])
            ->getResult();
    }

    private function fixSlot($slot)
    {
        $slot->setDice(max($slot->getDice(), $slot->getQuantity()));
        $slot->setQuantity(1);
    }
}
