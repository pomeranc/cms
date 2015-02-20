<?php

namespace App\Presenters;

/**
 * Support Presenter
 */
class SupportPresenter extends BasePresenter {

	public function renderDefault() {

		$navbar = [];
		$navbar[] = (object) ['name' => 'Pomoc', 'link' => 'Help:'];
		$navbar[] = (object) ['name' => 'Podpora systému'];

		$this->template->navbar = $navbar;

		$contact = (object) [
					'name' => 'Miloš Havlíček',
					'tel' => '+420 606 460 316',
					'email' => 'miloshavlicek@gmail.com'
		];

		$this->template->contact = $contact;
	}

}
