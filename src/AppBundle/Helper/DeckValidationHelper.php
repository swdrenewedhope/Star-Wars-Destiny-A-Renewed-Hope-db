<?php 

namespace AppBundle\Helper;
use AppBundle\Model\SlotCollectionDecorator;
use AppBundle\Model\SlotCollectionProviderInterface;

use function Functional\some;
use function Functional\every;
use function Functional\none;

class DeckValidationHelper
{
	
	public function __construct()
	{
		
	}
	
	public function getInvalidCards($deck)
	{
		$invalidCards = [];
		foreach ( $deck->getSlots() as $slot ) {
			if(! $this->canIncludeCard($deck, $slot->getCard())) {
				$invalidCards[] = $slot->getCard();
			}
		}
		return $invalidCards;
	}

	public function getNotMatchingCards($deck)
	{
		$notMatchingCards = [];
		foreach ( $deck->getSlots() as $slot ) {
			if(! $this->spotCharacterFaction($deck, $slot->getCard())) {
				$notMatchingCards[] = $slot->getCard();
			}
		}
		return $notMatchingCards;
	}

	public function hasSubtype($card, $types)
	{
		if(!is_array($types)) $types = [$types];

		return some($card->getSubtypes(), function($subtype) use ($types) {
			return some($types, function($type) use($subtype) {
				return $subtype->getCode() == $type;
			});
		});
	}
	
	public function canIncludeCard(SlotCollectionProviderInterface $deck, $card) {
		if(!$this->withinFormatSets($card, $deck->getFormat())) {
			return false;
		}

		if($card->getAffiliation()->getCode() === 'neutral') {
			return true;
		}

		if($card->getAffiliation()->getCode() === $deck->getAffiliation()->getCode()) {
			return true;
		}

		// Finn (AW #45) special case
		if($deck->getSlots()->getSlotByCode('01045') != NULL) {
			if(    $card->getAffiliation()->getCode()==='villain' 
				&& $card->getFaction()->getCode()==='red' 
				&& $this->hasSubtype($card, ['vehicle', 'weapon']))
			{
				return true;
			}
		}

		// Bo-Katan Kryze (WotF #89) special case
		if($deck->getSlots()->getSlotByCode('07089') != NULL) {
			if(    $card->getAffiliation()->getCode()==='villain' 
				&& $card->getFaction()->getCode()==='yellow' 
				&& $card->getType()->getCode()==='upgrade')
			{
				return true;
			}
		}

		// Leia Organa (AtG #90) special case
		if($deck->getSlots()->getSlotByCode('08090') != NULL) {
			if($card->getAffiliation()->getCode()==='villain')
			{
				return true;
			}
		}

		// Qi'Ra (AtG #135) special case
		if($deck->getSlots()->getSlotByCode('08135') != NULL) {
			if(    $card->getFaction()->getCode()==='yellow' 
				&& $card->getType()->getCode()==='event')
			{
				return true;
			}
		}

		// Enfys Nest (CONV #141) special case
		if($deck->getSlots()->getSlotByCode('09141') != NULL) {
			if($card->getType()->getCode()!=='character')
			{
				return true;
			}
		}

		// Enfys Nest's Marauder (CONV #142) special case
		if($deck->getSlots()->getSlotByCode('09142') != NULL) {
			if($card->getType()->getCode()!=='character')
			{
				return true;
			}
		}

		// Temporary Truce (SoH #119) special case
		if($deck->getSlots()->getSlotByCode('11119') != NULL) {
			return true;
		}
		if($deck->getSlots()->getSlotByCode('20128') != NULL) {
			return true;
		}
		
		// The Father (DoP #87) special case
		if($deck->getSlots()->getSlotByCode('22087') != NULL) {
			return true;
		}

		// Pong Krell (CM #3) special case
		if($deck->getSlots()->getSlotByCode('12003') != NULL) {
			if(    $card->getAffiliation()->getCode()==='hero' 
				&& $card->getFaction()->getCode()==='blue' 
				&& $card->getType()->getCode()!=='character')
			{
				return true;
			}
		}
		
		// Cassian Andor (FA #40) special case
		if($deck->getSlots()->getSlotByCode('14040') != NULL) {
			if(    $this->hasSubtype($card, ['intel'])
			    && $card->getType()->getCode()!=='character'
				&& $card->getType()->getCode()!=='plot')
			{
				return true;
			}
		}
		
		// Merrin (FA #63) special case
		if($deck->getSlots()->getSlotByCode('14063') != NULL) {
			if(    $card->getAffiliation()->getCode()==='villain' 
				&& $card->getFaction()->getCode()==='blue' 
				&& $this->hasSubtype($card, ['curse']))
			{
				return true;
			}
		}
		
		// IG-11 (HS #28) special case
		// Pt 1: Allow IG-11 with a hero engineer
		foreach ($deck->getSlots()->getCharacterArray() as $character) {
			if(    $character->getCard()->getAffiliation()->getCode()==='hero' 
				&& $character->getCard()->getType()->getCode()==='character'
				&& $this->hasSubtype($character->getCard(), ['engineer'])
				&& $card->getCode() === '16027')
			{
				return true;
			}
		}
		// Pt 2: Allow villain cards with IG-11
		if($deck->getSlots()->getSlotByCode('16027') != NULL) {
			if(    $card->getType()->getCode()!=='character'
			    && $card->getAffiliation()->getCode()==='villain')
			{
				return true;
			}
		}
		
		// Iden Versio (UH #18) special case
		// Pt 1: Allow Iden with a Red hero leader
		foreach ($deck->getSlots()->getCharacterArray() as $character) {
			if(    $character->getCard()->getAffiliation()->getCode()==='hero' 
				&& $character->getCard()->getFaction()->getCode()==='red' 
				&& $character->getCard()->getType()->getCode()==='character'
				&& $this->hasSubtype($character->getCard(), ['leader'])
				&& $card->getCode() === '18018')
			{
				return true;
			}
		}
		// Pt 2: Allow villain cards with Iden
		if($deck->getSlots()->getSlotByCode('18018') != NULL) {
			if(    $card->getType()->getCode()!=='character'
			    && $card->getAffiliation()->getCode()==='villain')
			{
				return true;
			}
		}
		

		// Insurgent Group (SA #82) special case
		if($deck->getSlots()->getSlotByCode('21082') != NULL) {
			if(    $card->getType()->getCode()!=='character'
			    && $card->getType()->getCode()!=='plot')
				return true;
		}
		
		// Boba Fett (SA #95) special case
		if($deck->getSlots()->getSlotByCode('21095') != NULL) {
			if(    $card->getType()->getCode()!=='character'
				&& $card->getType()->getCode()!=='plot'
			    && $card->getFaction()->getCode()==='yellow')
			{
				return true;
			}
		}
		
		// Barriss Offee (RES #2)
		if($deck->getSlots()->getSlotByCode('23002') != NULL) {
			if(    $card->getType()->getCode()==='event'
			    && $card->getFaction()->getCode()==='blue')
			{
				return true;
			}
		}
	
		// Mon Mothma (RES #60)
		if($deck->getSlots()->getSlotByCode('23060') != NULL) {
			if(    $card->getType()->getCode()==='support'
			    && $card->getFaction()->getCode()==='yellow')
			{
				return true;
			}
		}
		
		// Bix Caleen (RES #70)
		if($deck->getSlots()->getSlotByCode('23070') != NULL) {
			if(    $card->getFaction()->getCode()==='red'
				&& $this->hasSubtype($card, ['mod']))
			{
				return true;
			}
		}
		
		// Asajj Ventress (RES #87)
		if($deck->getSlots()->getSlotByCode('23087') != NULL) {
			if(    $card->getType()->getCode()==='downgrade')
			{
				return true;
			}
		}
		
		// Count Dooku (RES #88)
		if($deck->getSlots()->getSlotByCode('23088') != NULL) {
			if(    $card->getType()->getCode()==='upgrade'
			    && $card->getFaction()->getCode()==='blue')
			{
				return true;
			}
		}
		
		// Morgan Elspeth (AF #18)
		if($deck->getSlots()->getSlotByCode('24018') != NULL) {
			if(    $card->getAffiliation()->getCode()==='villain' 
				&& $card->getFaction()->getCode()==='blue')
			{
				return true;
			}
		}
		
		// Cal Kestis (AF #47)
		if($deck->getSlots()->getSlotByCode('24047') != NULL) {
			if(    $card->getType()->getCode()==='upgrade'
			    && $card->getFaction()->getCode()==='yellow')
			{
				return true;
			}
		}
		
		return false;
	}

	public function withinFormatSets($card, $format) {
		if(in_array($card->getSet()->getCode(), $format->getData()["sets"]))
			return true;

		if($card->getReprints() !== NULL) {
			foreach($card->getReprints() as $reprint) {
				if(in_array($reprint->getSet()->getCode(), $format->getData()["sets"]))
					return true;
			}
		}

		if($card->getReprintOf() !== NULL) {
			if(in_array($card->getReprintOf()->getSet()->getCode(), $format->getData()["sets"]))
				return true;
		}


		return false;
	}

	public function spotCharacterFaction(SlotCollectionProviderInterface $deck, $card) {
		$factions = $deck->getSlots()->getCharacterDeck()->getFactions();

		if($card->getFaction()->getCode() === 'gray' || in_array($card->getFaction()->getCode(), $factions)) {
			return true;
		}

		// Finn (AW #45) special case
		if($deck->getSlots()->getSlotByCode('01045') != NULL) {
			if(    $card->getAffiliation()->getCode()==='villain' 
				&& $card->getFaction()->getCode()==='red' 
				&& $this->hasSubtype($card, ['vehicle', 'weapon']))
			{
				return true;
			}
		}
		
		// Asajj Ventress (RES #87)
		if($deck->getSlots()->getSlotByCode('23087') != NULL) {
			if(    $card->getType()->getCode()==='downgrade')
			{
				return true;
			}
		}
		
		// Bix Caleen (RES #70)
		if($deck->getSlots()->getSlotByCode('23070') != NULL) {
			if(    $card->getFaction()->getCode()==='red'
				&& $this->hasSubtype($card, ['mod']))
			{
				return true;
			}
		}
		
		// Insurgent Group (SA #82) special case
		if($deck->getSlots()->getSlotByCode('21082') != NULL) {
			return true; // Just allow anything. Not sure of a sensible way to limit that to 4 cards - Ace Jon
		}
		
		// Mon Mothma (RES #60)
		if($deck->getSlots()->getSlotByCode('23060') != NULL) {
			if(    $card->getType()->getCode()==='support'
			    && $card->getFaction()->getCode()==='yellow')
			{
				return true;
			}
		}

		return false;
	}

	public function checkPlots(SlotCollectionProviderInterface $deck)
	{
		return every($deck->getSlots()->getPlotDeck(), function($slot) use($deck) {
			switch($slot->getCard()->getCode()) {
				//Retribution (AtG 54)
				case '08054':
					return some($deck->getSlots()->getCharacterDeck(), function($slot) {
						$card = $slot->getCard();
						$points = 0;
						if($card->getIsUnique())
						{
							$pointValues = preg_split('/\//', $card->getPoints());
							$points = intval($pointValues[$slot->getDice()-1], 10);
						}
						else
						{
							$points = intval($card->getPoints());
						}
						return $points >= 20;
					});
				//No Allegiance (AtG 155)
				case '08155':
					return none($deck->getSlots()->getCharacterDeck(), function($slot) {
						return in_array($slot->getCard()->getAffiliation()->getCode(), ["villain", "hero"]);
					});
				//Solidarity (AtG 156)
				case '08156':
					if(count($deck->getSlots()->getCharacterDeck()->getFactions()) > 1)
						return false;

					if(some($deck->getSlots()->getDrawDeck()->getContent(), function($slot, $code, $slots) {
						return max($slot["quantity"], $slot["dice"]) > 1;
					}))
						return false;

					return true;
				//Temporary Truce (SoH 119)
			    case '11119':
			    	if(some($deck->getSlots()->getDrawDeck()->getSlots(), function($slot) {
						return $slot->getCard()->getFaction()->getCode() == 'gray';
					}))
			    		return false;

			    	if(some($deck->getSlots()->getCharacterDeck()->getSlots(), function($slot) {
						return $slot->getCard()->getName() !== 'Rey' && $slot->getCard()->getName() !== 'Kylo Ren';
					})) 
			    		return false;
				case '20128':
			    	if(some($deck->getSlots()->getDrawDeck()->getSlots(), function($slot) {
						return $slot->getCard()->getFaction()->getCode() == 'gray';
					}))
			    		return false;

			    	if(some($deck->getSlots()->getCharacterDeck()->getSlots(), function($slot) {
						return $slot->getCard()->getName() !== 'Rey' && $slot->getCard()->getName() !== 'Kylo Ren';
					})) 
			    		return false;

			    	return true;
			    //Spectre Cell (CM 104)
			    case '12104': 
			    	return every($deck->getSlots()->getCharacterDeck(), function($slot) {
			    		foreach($slot->getCard()->getSubtypes() as $subtype)
						{
							if($subtype->getCode() == 'spectre')
							{
								return true;
							}
						}
						return false;
			    	});
				//Insurgent Group (SA 82)
			    case '21082': 
			    	return every($deck->getSlots()->getCharacterDeck(), function($slot) {
			    		foreach($slot->getCard()->getSubtypes() as $subtype)
						{
							if($subtype->getCode() == 'partisan')
							{
								return true;
							}
						}
						return false;
			    	});
				
				//The Father (DoP 87)
			    case '22087':

			    	if(some($deck->getSlots()->getCharacterDeck()->getSlots(), function($slot) {
						return $slot->getCard()->getName() !== 'The Son' && $slot->getCard()->getName() !== 'The Daughter';
					})) 
			    		return false;

			    	return true;
					
				default:
					return true;
			}
		});
	}

	private function getDeckSize(SlotCollectionProviderInterface $deck)
	{
		$size = $deck->getSlots()->getDrawDeck()->countCards();

		if($deck->getSlots()->isSlotIncluded("09114"))
		{
			$moves = 0;
			foreach($deck->getSlots() as $slot)
			{
				$card = $slot->getCard();
				if($card->getType()->getCode()=="event")
				{
					foreach($card->getSubtypes() as $subtype)
					{
						if($subtype->getCode() === "move") {
							$moves += $slot->getQuantity();
						}
					}
				}
			}

			if($moves > 0)
			{
				$size -= min($moves, 2);
			}
		}

		return $size;
	}

	public function getRestrictedCount(SlotCollectionProviderInterface $deck)
	{
		if(array_key_exists("restricted", $deck->getFormat()->getData())) {
			$restrictedList = $deck->getFormat()->getData()["restricted"];
			return array_reduce($restrictedList, function($sum, $code) use ($deck) {
				return $sum + ($deck->getSlots()->isSlotIncluded($code) ? 1 : 0);
			}, 0);
		}
		return 0;
	}
	
	public function findProblem(SlotCollectionProviderInterface $deck)
	{
		$deckSize = $deck->getSlots()->isSlotIncluded("15101") ? 40 : 30; // CT-4040
		$deckSize = $deck->getSlots()->isSlotIncluded("21091") ? ($deckSize+4) : $deckSize; // Strategic Discipline
		if($this->getDeckSize($deck) != $deckSize) {
			return 'incorrect_size';
		}

		if($deck->getSlots()->getCharacterPoints()+$deck->getSlots()->getPlotPoints() > 30) {
			return 'too_many_points';
		}

		if(count($deck->getSlots()->getBattlefieldDeck()) == 0) {
			return 'no_battlefield';
		}

		if(count($deck->getSlots()->getBattlefieldDeck()) > ($deck->getSlots()->getSlotByCode('07127') != NULL ? 2 : 1)) {
			return 'too_many_battlefields';
		}

		if(!$this->checkPlots($deck)) {
			return 'plot';
		}

		$maxLimitExceeded = $deck->getSlots()->isSlotIncluded("08143") || $deck->getSlots()->isSlotIncluded("09114") ? 2 : 0;
		$limitExceeded = 0;
		foreach($deck->getSlots()->getCopiesAndDeckLimit() as $cardName => $value) {
			if($value['deck_limit'] && ($value['copies'] - $value['deck_limit']) > 1) return 'too_many_copies';
			if($value['deck_limit'] && $value['copies'] > $value['deck_limit']) $limitExceeded++;
			if($limitExceeded > $maxLimitExceeded)
				return 'too_many_copies';
		}

		/* Leia and Enfys Nest limits unimplemented until official aclarations about using them with Finn, Qi'Ra an Bo-Katan
		if($deck->getSlots()->isSlotIncluded('08090') && $deck->getSlots()->getCountByAffiliation()["villain"] > 5)
			return 'too_many_copies';

		if($deck->getSlots()->isSlotIncluded('09141') || $deck->getSlots()->isSlotIncluded('09142'))
		{
			$limit = $deck->getSlots()->isSlotIncluded('09141') ? 2 : 1;
			$otherAffiliation = $deck->getAffiliation()->getCode() == 'villain' ? 'hero' : 'villain';
			if($deck->getSlots()->getCountByAffiliation()[$otherAffiliation] > $limit) 
				return 'too_many_copies';
		}
		*/

		if($deck->getSlots()->isSlotIncluded('12003')) {
			$heroCards = count($deck->getSlots()->getSlotsByAffiliation()["hero"]);
			$heroCopies = $deck->getSlots()->getCountByAffiliation()["hero"];
			if($heroCopies > $heroCards || $heroCards > 4) {
				return 'too_many_copies';
			}
		}

		if(!empty($this->getInvalidCards($deck))) {
			return 'invalid_cards';
		}
		
		if(!empty($this->getNotMatchingCards($deck))) {
			return 'faction_not_included';
		}

		if($this->getRestrictedCount($deck) > 1) {
			return 'restricted_list';
		}

		return null;
	}	
}