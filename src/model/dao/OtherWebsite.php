<?php

namespace App;

use Doctrine\ORM\Mapping as ORM;
use \Kdyby\Doctrine\Entities;

/**
 * @ORM\Entity
 * @ORM\Table(name="sys_otherweb")
 */
class OtherWebsite extends Entities\BaseEntity {

	use Entities\Attributes\Identifier;

	/**
	 * @ORM\Column(type="text")
	 */
	public $name;

	/**
	 * @ORM\Column(type="text")
	 */
	public $url;

	/**
	 * @ORM\Column(type="text")
	 */
	public $orderLine;

	/**
	 * @ORM\Column(type="text")
	 */
	public $groupLine;

}