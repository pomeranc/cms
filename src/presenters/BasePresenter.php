<?php

namespace App\Presenters;

use App;
use App\Webinfo;
use App\OtherWebsite;
use App\Cothema\Admin;
use Cothema\Model as CModel;
use Cothema\Model\User\User;
use Cothema\Model\User\Permissions;
use Cothema\CMSBE\Service\PagePin;
use IPub;
use Nette;
use Nette\Application;
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage as CacheFileStorage;
use WebLoader;

/**
 * Base presenter for all application presenters.
 *
 * @author Milos Havlicek <miloshavlicek@gmail.com>
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter {

	use IPub\Gravatar\TGravatar,
	 IPub\Permissions\TPermission;

	/** @var \Kdyby\Doctrine\EntityManager @inject */
	public $em;

	/** @persistent */
	public $locale;

	/** @var \Nette\Mail\IMailer @inject */
	public $mailer;

	/** @var \DK\Menu\UI\ICustomTopControlFactory @inject */
	public $menuLeftTopFactory;

	/** @var \DK\Menu\UI\ICustomBottomControlFactory @inject */
	public $menuLeftBottomFactory;

	/** @var \Kdyby\Translation\Translator @inject */
	public $translator;

	/**
	 * Checks authorization.
	 *
	 * @param string $element
	 * @return void
	 * @throws Application\ForbiddenRequestException
	 */
	public function checkRequirements($element) {
		try {
			parent::checkRequirements($element);

			if (!$this->requirementsChecker->isAllowed($element)) {
				throw new Application\ForbiddenRequestException;
			}
		} catch (Application\ForbiddenRequestException $e) {
			$this->flashMessage('Pro vstup do požadované sekce musíte být přihlášen/a s příslušným oprávněním.');

			if (!$this->user->isLoggedIn()) {
				$this->redirect('Sign:in', ['backSignInUrl' => $this->getHttpRequest()->url->path]);
			} elseif (!$this->isLinkCurrent('Homepage:')) {
				$this->redirect('Homepage:');
			} else {
				$this->redirect('Sign:in');
			}
		}
	}

	/**
	 *
	 * @return string
	 */
	protected function getWWWDir() {
		return realpath(DIR_WWW);
	}

	/**
	 *
	 * @return void
	 */
	public function handlePinIt() {
		try {
			$pinned = (new PagePin($this, $this->em))->pinIt();
			$this->flashMessage('Stránka "' . $pinned['title'] . '" byla připnuta na Hlavní panel.', 'success');
		} catch (\Exception $e) {
			$this->flashMessage('Došlo k chybě.', 'danger');
		}

		$this->redirect('this');
	}

	/**
	 *
	 * @return void
	 */
	public function handleUnpinIt() {
		try {
			(new PagePin($this, $this->em))->unpinIt();
			$this->flashMessage('Stránka byla odebrána z Hlavního panelu.', 'warning');
		} catch (\Exception $e) {
			$this->flashMessage('Došlo k chybě.', 'danger');
		}

		$this->redirect('this');
	}

	/**
	 *
	 * @return boolean|NULL
	 */
	public function isPinned() {
		try {
			return (new PagePin($this, $this->em))->isPinned();
		} catch (\Eception $e) {
			return NULL;
		}
	}

	/**
	 *
	 * @return boolean|NULL
	 */
	public function isPinable() {
		try {
			return (new PagePin($this, $this->em))->isPinable();
		} catch (\Exception $e) {
			return NULL;
		}
	}

	/**
	 *
	 * @param string|NULL $class
	 * @return Nette\Application\UI\ITemplate
	 */
	protected function createTemplate($class = NULL) {
		$template = parent::createTemplate($class);

		$template->_gravatar = $this->gravatar;

		$this->translator->createTemplateHelpers()
				->register($template->getLatte());

		return $template;
	}

	/**
	 * CSS stylesheet loading.
	 * @return WebLoader\Nette\CssLoader
	 */
	public function createComponentCssScreen() {
		return $this->lessComponentWrapper(['screen.less'], 'screen,projection,tv');
	}

	/**
	 * CSS stylesheet loading.
	 * @return WebLoader\Nette\CssLoader
	 */
	public function createComponentCssPrint() {
		return $this->lessComponentWrapper(['print.css'], 'print');
	}

	/**
	 * CSS stylesheet loading.
	 * @return WebLoader\Nette\CssLoader
	 */
	public function createComponentCssAdminLTE() {
		return $this->lessComponentWrapper(['AdminLTE.css'], FALSE, __DIR__ . '/../../../admin-lte/css');
	}

	/**
	 * JavaScript loading.
	 * @return WebLoader\Nette\JavaScriptLoader
	 */
	public function createComponentJsJquery() {
		return $this->jsComponentWrapper(['jquery.js']);
	}

	/**
	 * JavaScript loading.
	 * @return WebLoader\Nette\JavaScriptLoader
	 */
	public function createComponentJsMain() {
		return $this->jsComponentWrapper(['main.js']);
	}

	/**
	 * JavaScript loading.
	 * @return WebLoader\Nette\JavaScriptLoader
	 */
	public function createComponentJsAdminLTE() {
		return $this->jsComponentWrapper(['app.js', '../plugins/iCheck/icheck.min.js'], __DIR__ . '/../../../admin-lte/js/AdminLTE');
	}

	/**
	 * JavaScript loading.
	 * @return WebLoader\Nette\JavaScriptLoader
	 */
	public function createComponentJsNetteForms() {
		return $this->jsComponentWrapper(['netteForms.js']);
	}

	/**
	 * @return \DK\Menu\UI\Control
	 */
	protected function createComponentMenuLeftTop() {
		return $this->menuLeftTopFactory->create();
	}

	/**
	 * @return \DK\Menu\UI\Control
	 */
	protected function createComponentMenuLeftBottom() {
		return $this->menuLeftBottomFactory->create();
	}

	/**
	 * @param string $jsDir
	 */
	private function jsComponentWrapper(array $fileNames, $jsDir = NULL) {
		if ($jsDir === NULL) {
			$jsDir = __DIR__ . '/../scripts';
		}

		$outputDirName = '/tmp/js';

		$fileCollection = new WebLoader\FileCollection($jsDir);
		$fileCollection->addFiles($fileNames);

		$name = strtolower(substr($this->name, strrpos($this->name, ':') + 1)) . '.css';
		if (file_exists($jsDir . '/' . $name)) {
			$files->addFile($name);
		}

		$compiler = WebLoader\Compiler::createJsCompiler($fileCollection, $this->context->parameters['wwwDir'] . $outputDirName);

		$control = new WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . $outputDirName);

		return $control;
	}

	/**
	 * @param array $fileNames
	 * @param string|FALSE $media
	 * @param string $stylesDir
	 */
	private function lessComponentWrapper(array $fileNames, $media = NULL, $stylesDir = NULL) {
		if ($media === NULL) {
			$media = 'screen,projection,tv';
		}

		if ($stylesDir === NULL) {
			$stylesDir = __DIR__ . '/../styles';
		}

		$outputDirName = '/tmp/css';

		$fileCollection = new WebLoader\FileCollection($stylesDir);
		$fileCollection->addFiles($fileNames);

		$name = strtolower(substr($this->name, strrpos($this->name, ':') + 1)) . '.css';
		if (file_exists($stylesDir . '/' . $name)) {
			$files->addFile($name);
		}

		$compiler = WebLoader\Compiler::createCssCompiler($fileCollection, $this->context->parameters['wwwDir'] . $outputDirName);

		$filter = new WebLoader\Filter\LessFilter;
		$compiler->addFileFilter($filter);

		$control = new WebLoader\Nette\CssLoader($compiler, $this->template->basePath . $outputDirName);

		if (is_string($media)) {
			$control->setMedia($media);
		}

		return $control;
	}

	/**
	 *
	 * @return string|NULL
	 */
	private function getActualNameday() {
		return $this->getNameday(date('j'), date('n'));
	}

	/**
	 * @param string $day
	 * @param string $month
	 * @return string|NULL
	 */
	private function getNameday($day, $month) {
		$dao = $this->em->getRepository(Admin\Nameday::class);
		$nameday = $dao->findBy(['day' => (int) $day, 'month' => (int) $month]);

		return isset($nameday[0]) ? $nameday[0] : NULL;
	}

	public function beforeRender() {
		parent::beforeRender();

		if ($this->isAjax()) {
			$this->redrawControl('content');
			$this->redrawControl('navMenu');
		}

		$this->template->actualDate = date('j. n. Y');

		$this->template->nameday = $this->getActualNameday();

		if ($this->user->isLoggedIn()) {
			Admin\LogActivityRepository::logActivity($this->em, $this->user->id);
		}

		if (($this->getPresenter()->name == 'Sign' && $this->getAction() == 'in') || ($this->getPresenter()->name == 'AboutWebapp')) {

		} else {
			$this->permissions('admin');
		}

		if ($this->user->isLoggedIn()) {
			$actualUserDao = $this->em->getRepository(CModel\User\User::class);
			$actualUser = $actualUserDao->find($this->getUser()->id);

			$custDao = $this->em->getRepository(App\Cothema\Admin\Custom::class);
			$cust = $custDao->findAll();
			$customOut = [];
			foreach ($cust as $custOne) {
				$userCustDao = $this->em->getRepository(App\Cothema\Admin\UserCustom::class);
				$userCust = $userCustDao->findBy(['user' => $this->getUser()->id, 'custom' => $custOne->id]);

				if (isset($userCust[0])) {
					$customOut[$custOne->alias] = $userCust[0]->custVal;
				} else {
					$customOut[$custOne->alias] = $custOne->defVal;
				}
			}
			$this->template->custom = (object) $customOut;


			$this->template->actualUser = $actualUser;
		} else {
			$custDao = $this->em->getRepository(App\Cothema\Admin\Custom::class);
			$cust = $custDao->findAll();
			$customOut = [];
			foreach ($cust as $custOne) {

				$customOut[$custOne->alias] = $custOne->defVal;
			}
			$this->template->custom = (object) $customOut;

			$this->template->actualUser = NULL;
		}

		$cacheStorage = new CacheFileStorage(DIR_ROOT . '/temp/cache');
		$beCache = new Cache($cacheStorage, 'Cothema.BE');

		$webinfo = $beCache->load('webInfo', function() use ($beCache) {
			return $beCache->save('webInfo', $this->getWebInfo(), [Cache::EXPIRE => '20 minutes']);
		});

		$this->template->companyName = $webinfo->webName;
		$this->template->companyFullName = $webinfo->company;
		$this->template->companyWebsite = $webinfo->website;
		$this->template->urlStats = $webinfo->urlStats;
		$this->template->webinfo = $webinfo;
		$this->template->isPinned = $this->isPinned();
		$this->template->isPinable = $this->isPinable();

		$this->template->menu = $beCache->load('items', function() use ($beCache) {
			return $beCache->save('items', $this->getBEMenuItems(), [Cache::EXPIRE => '20 minutes']);
		});

		$this->template->otherWebsites = $beCache->load('otherWebsites', function() use ($beCache) {
			return $beCache->save('otherWebsites', $this->getOtherWebsites(), [Cache::EXPIRE => '20 minutes']);
		});

		$dirTemplates = __DIR__ . '/templates';
		$this->template->mainLayoutPath = $dirTemplates . '/@layout.latte';

		if ($this->getUser()->id) {
			$idUser = $this->getUser()->id;

			$profileUser = $beCache->load('activeUser_' . $idUser, function() use ($beCache, $idUser) {
				return $beCache->save('activeUser_' . $idUser, $this->getActualUserFromDb(), [Cache::EXPIRE => '20 minutes']);
			});

			$this->template->profileUser = $profileUser;
		}
	}

	private function getActualUserFromDb() {
		$dao = $this->em->getRepository(CModel\User\User::class);
		return $dao->find($this->getUser()->id);
	}

	private function getWebInfo() {
		$webinfoDao = $this->em->getRepository(Webinfo::class);
		return $webinfoDao->find(1);
	}

	private function getOtherWebsites() {
		$dao = $this->em->getRepository(OtherWebsite::class);
		$otherWebsites = $dao->findBy(['groupLine' => NULL], ['orderLine' => 'ASC']);
		unset($dao);

		$c = 0;
		foreach ($otherWebsites as $otherWebsitesOne) {
			$c++;

			if ($c == 1) {
				$handleOtherW = [];
				$handleOtherW['groupName'] = 'Nezařazené';
				$handleOtherW['items'] = [];
			}

			$handleOtherW['items'][] = $otherWebsitesOne;
		}

		if ($c > 0) {
			$otherWebsites[] = (object) $handleOtherW;
		}

		$otherWebsitesGroupDao = $this->em->getRepository(App\OtherWebsiteGroup::class);
		$otherWebsitesGroup = $otherWebsitesGroupDao->findBy([], ['orderLine' => 'ASC']);

		foreach ($otherWebsitesGroup as $otherWebsitesGroupOne) {
			$dao = $this->em->getRepository(OtherWebsite::class);
			$otherWebsitesB = $dao->findBy(['groupLine' => $otherWebsitesGroupOne->id], ['orderLine' => 'ASC']);
			unset($dao);

			$handleOtherWB = [];
			$handleOtherWB['groupName'] = $otherWebsitesGroupOne->name;

			$handleOtherWB['items'] = [];
			foreach ($otherWebsitesB as $otherWebsitesBOne) {
				$handleOtherWB['items'][] = $otherWebsitesBOne;
			}

			$otherWebsites[] = (object) $handleOtherWB;
		}

		return $otherWebsites;
	}

	/**
	 *
	 * @return array
	 */
	private function getBEMenuItems() {
		$menu = [];
		$beMenuDao = $this->em->getRepository(App\BEMenu::class);
		$beMenu = $beMenuDao->findBy(['parent' => NULL], ['orderLine' => 'ASC']);

		// TODO: recursive - be careful - cycle!

		foreach ($beMenu as $beMenuOne) {
			$menuHandle = [];
			$menuHandle['id'] = $beMenuOne->id;
			$menuHandle['nLink'] = $beMenuOne->nLink;
			$menuHandle['name'] = $beMenuOne->name;
			$menuHandle['orderLine'] = $beMenuOne->orderLine;
			$menuHandle['parent'] = $beMenuOne->parent;
			$menuHandle['module'] = $beMenuOne->module;
			$menuHandle['faIcon'] = $beMenuOne->faIcon;

			// Find childs
			$beSubmenuDao = $this->em->getRepository(App\BEMenu::class);
			$beSubmenu = $beSubmenuDao->findBy(['parent' => $beMenuOne->id], ['orderLine' => 'ASC']);

			$menuHandle['childs'] = $beSubmenu;

			$menu[] = (object) $menuHandle;
		}

		return $menu;
	}

	/*
	 * @param mixed $role if array = OR (one of)
	 */

	protected function permissions($role) {
		try {
			$ok = (is_array($role)) ? $this->permissionsRoleArray($role) : $this->permissionsRole($role);

			if (!$ok) {
				throw new \Exception('You do not have sufficient permissions.');
			}
		} catch (\Exception $e) {

			if (is_array($role)) {
				$this->flashMessage('Pro vstup do této sekce musíte být přihlášen/a s příslušným oprávněním (' . implode(' / ', $role) . ').');
			} else {
				$this->flashMessage('Pro vstup do této sekce musíte být přihlášen/a s příslušným oprávněním (' . $role . ').');
			}

			$this->redirect('Sign:in', ['backSignInUrl' => $this->getHttpRequest()->url->path]);
		}
	}

	/**
	 * @param array $role
	 * @return boolean
	 */
	private function permissionsRoleArray(array $role) {
		foreach ($role as $roleOne) {
			$ok = $this->permissionsRole($roleOne);

			if ($ok) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 *
	 * @param string $role
	 * @return boolean
	 */
	private function permissionsRole($role) {
		return ($this->user->isInRole($role)) ? TRUE : FALSE;
	}

	/**
	 * @param string $section
	 * @return object
	 */
	protected function getPermissionsSection($section) {
		$userSignedDao = $this->em->getRepository(User::class);
		$userSigned = $userSignedDao->find($this->user->id);

		$permissionsDao = $this->em->getRepository(Permissions::class);
		$permissions = $permissionsDao->findBy(['user' => $userSigned, 'section' => $section]);

		if (isset($permissions[0])) {
			return $permissions[0];
		}

		return (object) ['section' => (string) $section, 'allowRead' => FALSE, 'allowWrite' => FALSE, 'allowDelete' => FALSE];
	}

	/**
	 * return void
	 */
	protected function notYetImplemented() {
		$this->flashMessage('POZOR! Tato funkce ještě není zcela implementována!', 'danger');
	}

}
