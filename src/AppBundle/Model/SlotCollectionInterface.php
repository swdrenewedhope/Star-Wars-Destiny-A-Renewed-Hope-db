<?php

namespace AppBundle\Model;

interface SlotCollectionInterface extends \Countable, \IteratorAggregate, \ArrayAccess
{

	public function countCards();
	public function getIncludedSets();
	public function getSlotByCode($code);
	public function isSlotIncluded($code);
	public function getSlotsByType();
	public function getSlotsByAffiliation();
	public function getCountByType();
	
	/**
	 * Get all slot counts sorted by faction code
	 * @return array
	 */
	public function getCountByFaction();

	/**
	 * Get all slot counts sorted by affiliation code
	 * @return array
	 */
	public function getCountByAffiliation();

	/**
	 * Get battlefield(s) as slots
	 * @return \AppBundle\Model\SlotCollectionInterface
	 */
	public function getBattlefieldDeck();
	
	/**
	 * Get the draw deck
	 * @return \AppBundle\Model\SlotCollectionInterface
	 */
	public function getDrawDeck();

	/**
	 * Get character row info (with non-unique character repeated)
	 * @return \AppBundle\Model\SlotCollectionInterface
	 */
	public function getCharacterRow();

	/**
	 * Get the character deck
	 * @return \AppBundle\Model\SlotCollectionInterface
	 */
	public function getCharacterDeck();

	/**
	 * Get character points
	 * @return integer
	 */
	public function getCharacterPoints();

	/**
	 * Get the character deck
	 * @return \AppBundle\Model\SlotCollectionInterface
	 */
	public function getPlotDeck();

	/**
	 * Get character points
	 * @return integer
	 */
	public function getPlotPoints();

	/**
	 * Get factions in an array (colors)
	 * @return array
	 */
	public function getFactions();

	
	/**
	 * Get the content as an array card_code => qty
	 * @return array
	 */
	public function getContent();
}
