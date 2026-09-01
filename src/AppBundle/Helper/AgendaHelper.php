<?php

namespace AppBundle\Helper;

use Doctrine\ORM\EntityManager;
use AppBundle\Entity\Card;
use AppBundle\Entity\Faction;

class AgendaHelper
{
	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}
	
	public function getMinorFactionCode(Card $agenda) {
		if(empty($agenda)) {
			return null;
		}

		$banners_core_set = [
				'01198' => 'baratheon',
				'01199' => 'greyjoy',
				'01200' => 'lannister',
				'01201' => 'martell',
				'01202' => 'thenightswatch',
				'01203' => 'stark',
				'01204' => 'targaryen',
				'01205' => 'tyrell'
		];
		if(isset($banners_core_set[$agenda->getCode()])) {
			return $banners_core_set[$agenda->getCode()];
		}
		return null;
	}

	public function getMinorFaction(Card $agenda) {
		$code = $this->getMinorFactionCode($agenda);
		if($code) {
			return $this->entityManager->getRepository('AppBundle:Faction')->findOneBy([ 'code' => $code ]);
		}
		return null;
	}
}