<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao;


/**
 * Front end module "close account".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleCloseAccount extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_password';


	/**
	 * Display a wildcard in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			/** @var \BackendTemplate|object $objTemplate */
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['closeAccount'][0]) . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		// Return if there is no logged in user
		if (!FE_USER_LOGGED_IN)
		{
			return '';
		}

		return parent::generate();
	}


	/**
	 * Generate the module
	 */
	protected function compile()
	{
		$this->import('FrontendUser', 'User');

		// Initialize the password widget
		$arrField = array
		(
			'name' => 'password',
			'inputType' => 'text',
			'label' => $GLOBALS['TL_LANG']['MSC']['password'][0],
			'eval' => array('hideInput'=>true, 'preserveTags'=>true, 'mandatory'=>true, 'required'=>true, 'tableless'=>$this->tableless)
		);

		$objWidget = new \FormTextField(\FormTextField::getAttributesFromDca($arrField, $arrField['name']));
		$objWidget->rowClass = 'row_0 row_first even';

		// Validate widget
		if (\Input::post('FORM_SUBMIT') == 'tl_close_account')
		{
			$objWidget->validate();

			// Validate the password
			if (!$objWidget->hasErrors())
			{
				// Handle old sha1() passwords with an optional salt
				if (preg_match('/^[a-f0-9]{40}(:[a-f0-9]{23})?$/', $this->User->password))
				{
					list($strPassword, $strSalt) = explode(':', $this->User->password);
					$blnAuthenticated = ($strPassword === sha1($strSalt . $objWidget->value));
				}
				else
				{
					$blnAuthenticated = password_verify($objWidget->value, $this->User->password);
				}

				if (!$blnAuthenticated)
				{
					$objWidget->value = '';
					$objWidget->addError($GLOBALS['TL_LANG']['ERR']['invalidPass']);
				}
			}

			// Close account
			if (!$objWidget->hasErrors())
			{
				// HOOK: send account ID
				if (isset($GLOBALS['TL_HOOKS']['closeAccount']) && is_array($GLOBALS['TL_HOOKS']['closeAccount']))
				{
					foreach ($GLOBALS['TL_HOOKS']['closeAccount'] as $callback)
					{
						$this->import($callback[0]);
						$this->{$callback[0]}->{$callback[1]}($this->User->id, $this->reg_close, $this);
					}
				}

				$objMember = \MemberModel::findByPk($this->User->id);

				// Remove the account
				if ($this->reg_close == 'close_delete')
				{
					$objMember->delete();
					$this->log('User account ID ' . $this->User->id . ' (' . \Idna::decodeEmail($this->User->email) . ') has been deleted', __METHOD__, TL_ACCESS);
				}
				// Deactivate the account
				else
				{
					$objMember->disable = 1;
					$objMember->tstamp = time();
					$objMember->save();
					$this->log('User account ID ' . $this->User->id . ' (' . \Idna::decodeEmail($this->User->email) . ') has been deactivated', __METHOD__, TL_ACCESS);
				}

				$this->User->logout();

				// Check whether there is a jumpTo page
				if (($objJumpTo = $this->objModel->getRelated('jumpTo')) !== null)
				{
					$this->jumpToOrReload($objJumpTo->row());
				}

				$this->reload();
			}
		}

		$this->Template->fields = $objWidget->parse();

		$this->Template->formId = 'tl_close_account';
		$this->Template->action = \Environment::get('indexFreeRequest');
		$this->Template->slabel = specialchars($GLOBALS['TL_LANG']['MSC']['closeAccount']);
		$this->Template->rowLast = 'row_1 row_last odd';
		$this->Template->tableless = $this->tableless;
	}
}
