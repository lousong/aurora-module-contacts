<?php

class ContactsModule extends AApiModule
{
	public $oApiContactsManager = null;
	
	public function init() 
	{
		$this->incClass('contact-list-item');
		$this->incClass('contact');
		$this->incClass('group');
		$this->incClass('vcard-helper');

		$this->oApiContactsManager = $this->GetManager('main');
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('CreateAccount', array($this, 'onCreateAccountEvent'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
	}
	
	/**
	 * Obtaines list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array(
			'ContactsPerPage' => 20, // AppData.User.ContactsPerPage
			'ImportContactsLink' => '', // AppData.Links.ImportingContacts
			'Storages' => array('personal', 'global', 'shared'), // AppData.User.ShowPersonalContacts, AppData.User.ShowGlobalContacts, AppData.App.AllowContactsSharing
			'EContactsStorage' => (new \EContactsStorage)->getMap(),
			'EContactsPrimaryEmail' => (new \EContactsPrimaryEmail)->getMap(),
			'EContactsPrimaryPhone' => (new \EContactsPrimaryPhone)->getMap(),
			'EContactsPrimaryAddress' => (new \EContactsPrimaryAddress)->getMap(),
			'EContactSortField' => (new \EContactSortField)->getMap(),
		);
	}
	
	/**
	 * @param \CContact $oContact
	 * @param array $aContact
	 */
	private function populateContactObject(&$oContact, $aContact)
	{
		$bItsMe = $oContact->ItsMe;
		$oContact->PrimaryEmail = $aContact['PrimaryEmail'];

		$oContact->FullName = $aContact['FullName'];
		$oContact->FirstName = $aContact['FirstName'];
		$oContact->LastName = $aContact['LastName'];
		$oContact->NickName = $aContact['NickName'];
		$oContact->Skype = $aContact['Skype'];
		$oContact->Facebook = $aContact['Facebook'];
		
		$oContact->PersonalEmail = $aContact['PersonalEmail'];
		$oContact->PersonalAddress = $aContact['PersonalAddress'];
		$oContact->PersonalCity = $aContact['PersonalCity'];
		$oContact->PersonalState = $aContact['PersonalState'];
		$oContact->PersonalZip = $aContact['PersonalZip'];
		$oContact->PersonalCountry = $aContact['PersonalCountry'];
		$oContact->PersonalWeb = $aContact['PersonalWeb'];
		$oContact->PersonalFax = $aContact['PersonalFax'];
		$oContact->PersonalPhone = $aContact['PersonalPhone'];
		$oContact->PersonalMobile = $aContact['PersonalMobile'];
		
		$oContact->BusinessCompany = $aContact['BusinessCompany'];
		$oContact->BusinessJobTitle = $aContact['BusinessJobTitle'];
		$oContact->BusinessDepartment = $aContact['BusinessDepartment'];
		$oContact->BusinessOffice = $aContact['BusinessOffice'];
		$oContact->BusinessAddress = $aContact['BusinessAddress'];
		$oContact->BusinessCity = $aContact['BusinessCity'];
		$oContact->BusinessState = $aContact['BusinessState'];
		$oContact->BusinessZip = $aContact['BusinessZip'];
		$oContact->BusinessCountry = $aContact['BusinessCountry'];
		$oContact->BusinessFax = $aContact['BusinessFax'];
		$oContact->BusinessPhone = $aContact['BusinessPhone'];
		$oContact->BusinessWeb = $aContact['BusinessWeb'];
		
		$oContact->OtherEmail = $aContact['OtherEmail'];
		$oContact->Notes = $aContact['Notes'];
		if (!$bItsMe)
		{
			$oContact->BusinessEmail = $aContact['BusinessEmail'];
		}
		$oContact->BirthdayDay = $aContact['BirthdayDay'];
		$oContact->BirthdayMonth = $aContact['BirthdayMonth'];
		$oContact->BirthdayYear = $aContact['BirthdayYear'];


		$aGroupsIds = $aContact['GroupsIds'];
		$aGroupsIds = is_array($aGroupsIds) ? array_map('trim', $aGroupsIds) : array();
		$oContact->GroupsIds = implode(',', array_unique($aGroupsIds));
	}	
	
	private function downloadContacts($sSyncType)
	{
		$oAccount = $this->getDefaultAccountFromParam();
		if ($this->oApiCapabilityManager->isContactsSupported($oAccount))
		{
			$sOutput = $this->oApiContactsManager->export($oAccount->IdUser, $sSyncType);
			if (false !== $sOutput)
			{
				header('Pragma: public');
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="export.' . $sSyncType . '";');
				header('Content-Transfer-Encoding: binary');

				return $sOutput;
			}
		}
		return false;
	}
	
	/**
	 * @return array
	 */
	public function GetGroups()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
//		$sAuthToken = $this->getParamValue('AuthToken');
//		$iUserId = \CApi::getAuthenticatedUserId($sAuthToken);
//		$oAccount = $this->getDefaultAccountFromParam();

		$aList = false;
		//TODO use real user settings
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		$iUserId = \CApi::getAuthenticatedUserId();
		if ($iUserId > 0)
		{
			$aList = $this->oApiContactsManager->getGroupItems($iUserId);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return $aList;
	}
	
	/**
	 * @return array
	 */
	public function GetGroup()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oGroup = false;
		$oAccount = $this->getDefaultAccountFromParam();

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			$sGroupId = (string) $this->getParamValue('GroupId', '');
			$oGroup = $this->oApiContactsManager->getGroupById($oAccount->IdUser, $sGroupId);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return $oGroup;
	}
	
	/**
	 * @return array
	 */
	public function GetGroupEvents()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aEvents = array();
		$oAccount = $this->getDefaultAccountFromParam();

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			$sGroupId = (string) $this->getParamValue('GroupId', '');
			$aEvents = $this->oApiContactsManager->getGroupEvents($oAccount->IdUser, $sGroupId);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return $aEvents;
	}	
	
	/**
	 * @return array
	 */
	public function GetContacts($Offset = 0, $Limit = 20, $SortField = EContactSortField::Name, $SortOrder = ESortOrder::ASC, $Search = '', $GroupId = '', $Storage = EContactsStorage::All)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		$iTenantId = $Storage === EContactsStorage::Shared ? $oUser->IdTenant : null;
		$aContacts = $this->oApiContactsManager->getContactItems($oUser->iId, $SortField, $SortOrder, $Offset, $Limit, $Search, $GroupId, $iTenantId);
		$aList = array();
		if (is_array($aContacts))
		{
			foreach ($aContacts as $oContact)
			{
				$aList[] = array(
					'Id' => $oContact->iId,
					'Name' => $oContact->FullName,
					'Email' => $oContact->GetViewEmail(),
					'IsGroup' => false,
					'IsOrganization' => false,
					'ReadOnly' => false,
					'ItsMe' => false,
					'Global' => false,
					'SharedToAll' => false
				);
			}
		}

		return array(
			'ContactCount' => count($aList),
			'GroupId' => $GroupId,
			'Search' => $Search,
			'Storage' => $Storage,
			'List' => \CApiResponseManager::GetResponseObject($aList)
		);		
	}	
	
	/**
	 * @return array
	 */
	public function GetContact($ContactId, $Storage)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getContact($ContactId);
	}	

	public function GetAllContacts($Offset = 0, $Limit = 20, $SortField = 'Name', $SortOrder = 1, $Search = '', $GroupId = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->GetPersonalContacts($Offset, $Limit, $SortField, $SortOrder, $Search, $GroupId, true);
	}

	public function GetSharedContacts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$this->setParamValue('SharedToAll', '1');
		return $this->GetPersonalContacts();
	}

	public function GetPersonalContacts($Offset = 0, $Limit = 20, $SortField = \EContactSortField::Name, $SortOrder = 1, $Search = '', $GroupId = '', $All = false, $SharedToAll = false)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \CApi::getAuthenticatedUserId();
		$oUser = \CApi::getAuthenticatedUser();
		
		$iTenantId = $SharedToAll ? $oUser->IdTenant : null;
		
		$bAllowContactsSharing = true;
		$bAllowGlobalContacts = true;
		$bAllowPersonalContacts = true;
		if ($All && !$bAllowContactsSharing && $bAllowGlobalContacts)
		{
			$All = false;
		}

		$iCount = 0;
		$aList = array();
		
		if ($bAllowPersonalContacts)
		{
			if ($bAllowContactsSharing && 0 < $GroupId)
			{
				$iTenantId = $oUser->IdTenant;
			}
			
//			$iCount = $this->oApiContactsManager->getContactItemsCount(
//				$iUserId, $Search, '', $GroupId, $iTenantId, $All);
//
//			if (0 < $iCount)
//			{
				$aContacts = $this->oApiContactsManager->getContactItems(
					$iUserId, $SortField, $SortOrder, $Offset,
					$Limit, $Search, '', $GroupId, $iTenantId, $All);

				if (is_array($aContacts))
				{
					$aList = $aContacts;
				}
//			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return array(
			'ContactCount' => $iCount,
			'GroupId' => $GroupId,
			'Search' => $Search,
			'FirstCharacter' => '',
			'All' => $All,
			'List' => \CApiResponseManager::GetResponseObject($aList)
		);		
	}
	
	/**
	 * @return array
	 */
	public function GetContactsByEmails()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aResult = array();
		$oAccount = $this->getDefaultAccountFromParam();

		$sEmails = (string) $this->getParamValue('Emails', '');
		$aEmails = explode(',', $sEmails);

		if (0 < count($aEmails))
		{
			$oApiContacts = $this->oApiContactsManager;
			$oApiGlobalContacts = $this->GetManager('global');
			
			$bPab = $oApiContacts && $this->oApiCapabilityManager->isPersonalContactsSupported($oAccount);
			$bGab = $oApiGlobalContacts && $this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true);

			foreach ($aEmails as $sEmail)
			{
				$oContact = false;
				$sEmail = trim($sEmail);
				
				if ($bPab)
				{
					$oContact = $oApiContacts->getContactByEmail($oAccount->IdUser, $sEmail);
				}

				if (!$oContact && $bGab)
				{
					$oContact = $oApiGlobalContacts->getContactByEmail($oAccount, $sEmail);
				}

				if ($oContact)
				{
					$aResult[$sEmail] = $oContact;
				}
			}
		}

		return $aResult;
	}	
	
	/**
	 * @return array
	 */
	public function GetGlobalContacts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		$oApiGlobalContacts = $this->GetManager('global');

		$iOffset = (int) $this->getParamValue('Offset', 0);
		$iLimit = (int) $this->getParamValue('Limit', 20);
		$sSearch = (string) $this->getParamValue('Search', '');

//		$iSortField = \EContactSortField::Email;
//		$iSortOrder = \ESortOrder::ASC;
//
//		$this->populateSortParams($iSortField, $iSortOrder);

		$iCount = 0;
		$aList = array();

		if ($this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
		{
			$iCount = $oApiGlobalContacts->getContactItemsCount($oAccount, $sSearch);

			if (0 < $iCount)
			{
				$aContacts = $oApiGlobalContacts->getContactItems(
					$oAccount, $iSortField, $iSortOrder, $iOffset,
					$iLimit, $sSearch
				);

				$aList = (is_array($aContacts)) ? $aContacts : array();
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return array(
			'ContactCount' => $iCount,
			'Search' => $sSearch,
			'List' => $aList
		);
	}	
	
	
	
	/**
	 * @return array
	 */
	public function GetGlobalContact()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = false;
		$oAccount = $this->getDefaultAccountFromParam();
		$sContactId = (string) $this->getParamValue('ContactId', '');
		
		if ($this->oApiCapabilityManager->isGlobalContactsSupported($oAccount)) {

			$oApiGlobalContacts = $this->GetManager('global');
			$oContact = $oApiGlobalContacts->getContactById($oAccount, $sContactId);

		} else {

			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return $oContact;
	}	
	
	public function GetPersonalContact()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = false;
		$oAccount = $this->getDefaultAccountFromParam();
		$sContactId = (string) $this->getParamValue('ContactId', '');

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount)) {

			$bSharedToAll = '1' === (string) $this->getParamValue('SharedToAll', '0');
			$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;

			$oContact = $this->oApiContactsManager->getContactById($oAccount->IdUser, $sContactId, false, $iTenantId);
		} else {

			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}
		
		return $oContact;
	}
	
	public function GetSharedContact()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$this->setParamValue('SharedToAll', '1');
		return $this->GetPersonalContact();
	}
	
	
	public function DownloadContactsAsCSV()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->downloadContacts('csv');
	}
	
	public function DownloadContactsAsVCF()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->downloadContacts('vcf');
	}

	/**
	 * @return array
	 */
	public function GetContactByEmail()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = false;
		$oAccount = $this->getDefaultAccountFromParam();
		
		$sEmail = (string) $this->getParamValue('Email', '');

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount)) {
			
			$oContact = $this->oApiContactsManager->getContactByEmail($oAccount->IdUser, $sEmail);
		}

		if (!$oContact && $this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true)) {
			
			$oApiGContacts = $this->GetManager('global');
			if ($oApiGContacts) {
				
				$oContact = $oApiGContacts->getContactByEmail($oAccount, $sEmail);
			}
		}

		return $oContact;
	}	
	
	/**
	 * @return array
	 */
	public function GetSuggestions()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		$sSearch = (string) $this->getParamValue('Search', '');
		$bGlobalOnly = '1' === (string) $this->getParamValue('GlobalOnly', '0');
		$bPhoneOnly = '1' === (string) $this->getParamValue('PhoneOnly', '0');

		$aList = array();
		
		$iSharedTenantId = null;
		if ($this->oApiCapabilityManager->isSharedContactsSupported($oAccount) && !$bPhoneOnly)
		{
			$iSharedTenantId = $oAccount->IdTenant;
		}

		if ($this->oApiCapabilityManager->isContactsSupported($oAccount))
		{
			$aContacts = 	$this->oApiContactsManager->getSuggestItems($oAccount, $sSearch,
					\CApi::GetConf('webmail.suggest-contacts-limit', 20), $bGlobalOnly, $bPhoneOnly, $iSharedTenantId);

			if (is_array($aContacts))
			{
				$aList = $aContacts;
			}
		}

		return array(
			'Search' => $sSearch,
			'List' => $aList
		);
	}	
	
	public function DeleteSuggestion()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		$oAccount = $this->getDefaultAccountFromParam();

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			$sContactId = (string) $this->getParamValue('ContactId', '');
			$this->oApiContactsManager->resetContactFrequency($oAccount->IdUser, $sContactId);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return $mResult;
	}	
	
	public function UpdateSuggestTable()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		$aEmails = $this->getParamValue('Emails', array());
		$this->oApiContactsManager->updateSuggestTable($oAccount->IdUser, $aEmails);
	}
	
	/**
	 * 
	 * @param array $Contact
	 * @return boolean
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateContact($Contact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		
		$bAllowPersonalContacts = true;
		if ($bAllowPersonalContacts)
		{
			$oContact = \CContact::createInstance();
			$oContact->IdUser = $oUser->iId;
			$oContact->IdTenant = $oUser->IdTenant;
			$oContact->SharedToAll = $Contact['SharedToAll'];

			$this->populateContactObject($oContact, $Contact);

			$this->oApiContactsManager->createContact($oContact);
			return $oContact ? array(
				'IdContact' => $oContact->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return false;
	}	
	
	/**
	 * @return array
	 */
	public function UpdateContact($Contact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = $this->oApiContactsManager->getContact($Contact['ContactId']);
		$this->populateContactObject($oContact, $Contact);
		if (!$this->oApiContactsManager->updateContact($oContact, false))
		{
			return false;
		}
		return true;
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$bGlobal = '1' === $this->getParamValue('Global', '0');
//		$sContactId = $this->getParamValue('ContactId', '');
//
//		$bSharedToAll = '1' === $this->getParamValue('SharedToAll', '0');
//		$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;
//
//		if ($bGlobal && $this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
//		{
//			$oApiContacts = $this->GetManager('global');
//		}
//		else if (!$bGlobal && $this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$oApiContacts = $this->oApiContactsManager;
//		}
//
//		if ($oApiContacts)
//		{
//			$oContact = $oApiContacts->getContactById($bGlobal ? $oAccount : $oAccount->IdUser, $sContactId, false, $iTenantId);
//			if ($oContact)
//			{
//				$this->populateContactObject($oContact);
//
//				if ($oApiContacts->updateContact($oContact))
//				{
//					return true;
//				}
//				else
//				{
//					switch ($oApiContacts->getLastErrorCode())
//					{
//						case \Errs::Sabre_PreconditionFailed:
//							throw new \System\Exceptions\AuroraApiException(
//								\System\Notifications::ContactDataHasBeenModifiedByAnotherApplication);
//					}
//				}
//			}
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
	}
	
	/**
	 * @return array
	 */
	public function DeleteContacts($ContactsId, $SharedToAll)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aContacts = explode(',', $ContactsId);
		
		return $this->oApiContactsManager->deleteContacts($aContacts);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$aContactsId = explode(',', $this->getParamValue('ContactsId', ''));
//			$aContactsId = array_map('trim', $aContactsId);
//			
//			$bSharedToAll = '1' === (string) $this->getParamValue('SharedToAll', '0');
//			$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;
//
//			return $this->oApiContactsManager->deleteContacts($oAccount->IdUser, $aContactsId, $iTenantId);
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
	}	
	
	/**
	 * @return array
	 */
	public function UpdateShared()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		
		$aContactsId = explode(',', $this->getParamValue('ContactsId', ''));
		$aContactsId = array_map('trim', $aContactsId);
		
		$bSharedToAll = '1' === $this->getParamValue('SharedToAll', '0');
		$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			$oApiContacts = $this->oApiContactsManager;
		}

		if ($oApiContacts && $this->oApiCapabilityManager->isSharedContactsSupported($oAccount))
		{
			foreach ($aContactsId as $sContactId)
			{
				$oContact = $oApiContacts->getContactById($oAccount->IdUser, $sContactId, false, $iTenantId);
				if ($oContact)
				{
					if ($oContact->SharedToAll)
					{
						$oApiContacts->updateContactUserId($oContact, $oAccount->IdUser);
					}

					$oContact->SharedToAll = !$oContact->SharedToAll;
					$oContact->IdUser = $oAccount->IdUser;
					$oContact->IdDomain = $oAccount->IdDomain;
					$oContact->IdTenant = $oAccount->IdTenant;

					if (!$oApiContacts->updateContact($oContact))
					{
						switch ($oApiContacts->getLastErrorCode())
						{
							case \Errs::Sabre_PreconditionFailed:
								throw new \System\Exceptions\AuroraApiException(
									\System\Notifications::ContactDataHasBeenModifiedByAnotherApplication);
						}
					}
				}
			}
			
			return true;
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return false;
	}	
	
	/**
	 * @return array
	 */
	public function AddContactsFromFile()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		$mResult = false;

		if (!$this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		$sTempFile = (string) $this->getParamValue('File', '');
		if (empty($sTempFile))
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		$oApiFileCache = /* @var $oApiFileCache \CApiFilecacheManager */ \CApi::GetSystemManager('filecache');
		$sData = $oApiFileCache->get($oAccount, $sTempFile);
		if (!empty($sData))
		{
			$oContact = \CContact::createInstance();
			$oContact->InitFromVCardStr($oAccount->IdUser, $sData);

			if ($this->oApiContactsManager->createContact($oContact))
			{
				$mResult = array(
					'Uid' => $oContact->IdContact
				);
			}
		}

		return $mResult;
	}	
	
	/**
	 * @return array
	 */
	public function CreateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
			$oGroup = \CGroup::createInstance();
			$oGroup->IdUser = \CApi::getAuthenticatedUserId();
			
			$oGroup->populate($Group);
			
			$this->oApiContactsManager->createGroup($oGroup);
			return $oGroup ? array(
				'IdGroup' => $oGroup->iId
			) : false;
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
	}	
	
	/**
	 * @return array
	 */
	public function UpdateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$sGroupId = $this->getParamValue('GroupId', '');
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
			$oGroup = $this->oApiContactsManager->getGroup($Group['GroupId']);
			if ($oGroup)
			{
				$oGroup->populate($Group);
				return $this->oApiContactsManager->updateGroup($oGroup);
//				if ($this->oApiContactsManager->updateGroup($oGroup))
//				{
//					return true;
//				}
//				else
//				{
//					switch ($this->oApiContactsManager->getLastErrorCode())
//					{
//						case \Errs::Sabre_PreconditionFailed:
//							throw new \System\Exceptions\AuroraApiException(
//								\System\Notifications::ContactDataHasBeenModifiedByAnotherApplication);
//					}
//				}
			}
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(
//				\System\Notifications::ContactsNotAllowed);
//		}

		return false;
	}	
	
	/**
	 * @return array
	 */
	public function DeleteGroup()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			$sGroupId = $this->getParamValue('GroupId', '');

			return $this->oApiContactsManager->deleteGroup($oAccount->IdUser, $sGroupId);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return false;
	}
	
	/**
	 * @return array
	 */
	public function AddContactsToGroup()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			$sGroupId = (string) $this->getParamValue('GroupId', '');

			$aContactsId = $this->getParamValue('ContactsId', null);
			if (!is_array($aContactsId))
			{
				return false;
			}

			$oGroup = $this->oApiContactsManager->getGroupById($oAccount->IdUser, $sGroupId);
			if ($oGroup)
			{
				$aLocalContacts = array();
				$aGlobalContacts = array();
				
				foreach ($aContactsId as $aItem)
				{
					if (is_array($aItem) && 2 === count($aItem))
					{
						if ('1' === $aItem[1])
						{
							$aGlobalContacts[] = $aItem[0];
						}
						else
						{
							$aLocalContacts[] = $aItem[0];
						}
					}
				}

				$bRes1 = true;
				if (0 < count($aGlobalContacts))
				{
					$bRes1 = false;
					if (!$this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
					{
						throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
					}

					$bRes1 = $this->oApiContactsManager->addGlobalContactsToGroup($oAccount, $oGroup, $aGlobalContacts);
				}

				$bRes2 = true;
				if (0 < count($aLocalContacts))
				{
					$bRes2 = $this->oApiContactsManager->addContactsToGroup($oGroup, $aLocalContacts);
				}

				return $bRes1 && $bRes2;
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return false;
	}
	
	/**
	 * @return array
	 */
	public function RemoveContactsFromGroup()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount) ||
			$this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
		{
			$sGroupId = (string) $this->getParamValue('GroupId', '');

			$aContactsId = explode(',', $this->getParamValue('ContactsId', ''));
			$aContactsId = array_map('trim', $aContactsId);

			$oGroup = $this->oApiContactsManager->getGroupById($oAccount->IdUser, $sGroupId);
			if ($oGroup)
			{
				return $this->oApiContactsManager->removeContactsFromGroup($oGroup, $aContactsId);
			}

			return false;
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return false;
	}	
	
	public function SynchronizeExternalContacts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getParamValue('Account', null);
		if ($oAccount)
		{
			return $this->oApiContactsManager->SynchronizeExternalContacts($oAccount);
		}
		
	}
	
	public function onGetBodyStructureParts($aParts, &$aResultParts)
	{
		foreach ($aParts as $oPart)
		{
			if ($oPart instanceof \MailSo\Imap\BodyStructure && 
					($oPart->ContentType() === 'text/vcard' || $oPart->ContentType() === 'text/x-vcard'))
			{
				$aResultParts[] = $oPart;
			}
		}
	}
	
	public function UploadContacts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		if (!$this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}
		
		$aFileData = $this->getParamValue('FileData', null);
		$sAdditionalData = $this->getParamValue('AdditionalData', '{}');
		$aAdditionalData = @json_decode($sAdditionalData, true);

		$sError = '';
		$aResponse = array(
			'ImportedCount' => 0,
			'ParsedCount' => 0
		);

		if (is_array($aFileData)) {
			
			$sFileType = strtolower(\api_Utils::GetFileExtension($aFileData['name']));
			$bIsCsvVcfExtension  = $sFileType === 'csv' || $sFileType === 'vcf';

			if ($bIsCsvVcfExtension) {
				
				$oApiFileCacheManager = \CApi::GetSystemManager('filecache');
				$sSavedName = 'import-post-' . md5($aFileData['name'] . $aFileData['tmp_name']);
				if ($oApiFileCacheManager->moveUploadedFile($oAccount, $sSavedName, $aFileData['tmp_name'])) {
						$iParsedCount = 0;

						$iImportedCount = $this->oApiContactsManager->import(
							$oAccount->IdUser,
							$sFileType,
							$oApiFileCacheManager->generateFullFilePath($oAccount, $sSavedName),
							$iParsedCount
						);

					if (false !== $iImportedCount && -1 !== $iImportedCount) {
						
						$aResponse['ImportedCount'] = $iImportedCount;
						$aResponse['ParsedCount'] = $iParsedCount;
					} else {
						
						$sError = 'unknown';
					}

					$oApiFileCacheManager->clear($oAccount, $sSavedName);
				} else {
					
					$sError = 'unknown';
				}
			} else {
				
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::IncorrectFileExtension);
			}
		}
		else {
			
			$sError = 'unknown';
		}

		if (0 < strlen($sError)) {
			
			$aResponse['Error'] = $sError;
		}

		return $aResponse;
		
	}
	
	public function onExtendMessageData($oAccount, &$oMessage, $aData)
	{
		$oApiCapa = /* @var CApiCapabilityManager */ $this->oApiCapabilityManager;
		$oApiFileCache = /* @var CApiFilecacheManager */ CApi::GetSystemManager('filecache');

		foreach ($aData as $aDataItem) {
			
			if ($aDataItem['Part'] instanceof \MailSo\Imap\BodyStructure && 
					($aDataItem['Part']->ContentType() === 'text/vcard' || 
					$aDataItem['Part']->ContentType() === 'text/x-vcard')) {
				$sData = $aDataItem['Data'];
				if (!empty($sData) && $oApiCapa->isContactsSupported($oAccount)) {
					
					$oContact = \CContact::createInstance();
					$oContact->InitFromVCardStr($oAccount->IdUser, $sData);
					$oContact->initBeforeChange();

					$oContact->IdContact = 0;

					$bContactExists = false;
					if (0 < strlen($oContact->GetViewEmail()))
					{
						$oLocalContact = $this->oApiContactsManager->getContactByEmail($oAccount->IdUser, $oContact->GetViewEmail());
						if ($oLocalContact)
						{
							$oContact->IdContact = $oLocalContact->IdContact;
							$bContactExists = true;
						}
					}

					$sTemptFile = md5($sData).'.vcf';
					if ($oApiFileCache && $oApiFileCache->put($oAccount, $sTemptFile, $sData)) {
						
						$oVcard = CApiMailVcard::createInstance();

						$oVcard->Uid = $oContact->IdContact;
						$oVcard->File = $sTemptFile;
						$oVcard->Exists = !!$bContactExists;
						$oVcard->Name = $oContact->FullName;
						$oVcard->Email = $oContact->GetViewEmail();

						$oMessage->addExtend('VCARD', $oVcard);
					} else {
						
						CApi::Log('Can\'t save temp file "'.$sTemptFile.'"', ELogLevel::Error);
					}					
				}
			}
		}
	}	
	
	public function onCreateAccountEvent($oAccount)
	{
		if ($oAccount instanceof \CAccount)
		{
			$oContact = $this->oApiContactsManager->createContactObject();
			$oContact->BusinessEmail = $oAccount->Email;
			$oContact->PrimaryEmail = EContactsPrimaryEmail::Business;
			$oContact->FullName = $oAccount->FriendlyName;
			$oContact->Type = EContactType::GlobalAccounts;

			$oContact->IdTypeLink = $oAccount->IdUser;
			$oContact->IdDomain = 0 < $oAccount->IdDomain ? $oAccount->IdDomain : 0;
			$oContact->IdTenant = $oAccount->Domain ? $oAccount->Domain->IdTenant : 0;

			$this->oApiContactsManager->createContact($oContact);
		}
	}
	
    public function onGetMobileSyncInfo(&$aData)
	{
		$iUserId = \CApi::getAuthenticatedUserId();
		$oDavModule = \CApi::GetModuleDecorator('Dav');

		$sDavLogin = $oDavModule->GetLogin();
		$sDavServer = $oDavModule->GetServerUrl();

		$aData['Dav']['Contacts'] = [
			[
				'Name' => $this->i18N('LABEL_PERSONAL_CONTACTS', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
			],
			[
				'Name' => $this->i18N('LABEL_COLLECTED_ADDRESSES', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME
			],
			[
				'Name' => $this->i18N('LABEL_SHARED_ADDRESS_BOOK', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME
			],
			[
				'Name' => $this->i18N('LABEL_GLOBAL_ADDRESS_BOOK', $iUserId),
				'Url' => $sDavServer.'/gab'
			]
		];
		
		$aData['Dav']['Contacts'] = array(
			'PersonalContactsUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME,
			'CollectedAddressesUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME,
			'SharedWithAllUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
			'GlobalAddressBookUrl' => $sDavServer.'/gab'
		);
	}
	
	
}