<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @internal
 * 
 * @package Contacts
 * @subpackage Helpers
 */
class CApiContactsSyncCsv
{
	/**
	 * @var CApiContactsCsvFormatter
	 */
	protected $oApiContactsManager;

	/**
	 * @var CApiContactsCsvParser
	 */
	protected $oFormatter;

	/**
	 * @var CApiContactsCsvParser
	 */
	protected $oParser;

	public function __construct($oApiContactsManager)
	{
		$this->oApiContactsManager = $oApiContactsManager;
		$this->oFormatter = new CApiContactsCsvFormatter();
		$this->oParser = new CApiContactsCsvParser();
	}

	/**
	 * @param int $iUserId
	 *
	 * @return string
	 */
	public function Export($iUserId)
	{
		$iOffset = 0;
		$iRequestValue = 50;

		$sResult = '';

		$iCount = $this->oApiContactsManager->getContactItemsCount($iUserId);
		if (0 < $iCount)
		{
			while ($iOffset < $iCount)
			{
				$aList = $this->oApiContactsManager->getContactItemsWithoutOrder($iUserId, $iOffset, $iRequestValue);

				if (is_array($aList))
				{
					$oContactListItem = null;
					foreach ($aList as $oContactListItem)
					{
						$oContact = $this->oApiContactsManager->getContactById($iUserId, $oContactListItem->Id);
						if ($oContact)
						{
							$this->oFormatter->setContainer($oContact);
							$this->oFormatter->form();
							$sResult .= $this->oFormatter->getValue();
						}
					}

					$iOffset += $iRequestValue;
				}
				else
				{
					break;
				}
			}
		}

		return $sResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sTempFileName
	 * @param int $iParsedCount
	 * @param int $iGroupId
	 * @param bool $bIsShared
	 *
	 * @return int
	 */
	public function Import($iUserId, $sTempFileName, &$iParsedCount, $iGroupId, $bIsShared)
	{
		$iCount = -1;
		$iParsedCount = 0;
		if (file_exists($sTempFileName))
		{
			$aCsv = api_Utils::CsvToArray($sTempFileName);
			if (is_array($aCsv))
			{
				$oApiUsersManager = CApi::GetSystemManager('users');
				$oAccount = $oApiUsersManager->getDefaultAccount($iUserId);

				$iCount = 0;
				foreach ($aCsv as $aCsvItem)
				{
					set_time_limit(30);

					$this->oParser->reset();

					$oContact = new CContact();
					$oContact->IdUser = $iUserId;

					$this->oParser->setContainer($aCsvItem);
					$aParameters = $this->oParser->getParameters();

					foreach ($aParameters as $sPropertyName => $mValue)
					{
						if ($oContact->IsProperty($sPropertyName))
						{
							$oContact->{$sPropertyName} = $mValue;
						}
					}

					if (0 === strlen($oContact->FullName))
					{
						$oContact->FullName = trim($oContact->FirstName.' '.$oContact->LastName);
					}
					
					if (0 !== strlen($oContact->PersonalEmail))
					{
						$oContact->PrimaryEmail = \EContactsPrimaryEmail::Personal;
						$oContact->ViewEmail = $oContact->PersonalEmail;
					}
					else if (0 !== strlen($oContact->BusinessEmail))
					{
						$oContact->PrimaryEmail = \EContactsPrimaryEmail::Business;
						$oContact->ViewEmail = $oContact->BusinessEmail;
					}
					else if (0 !== strlen($oContact->OtherEmail))
					{
						$oContact->PrimaryEmail = \EContactsPrimaryEmail::Other;
						$oContact->ViewEmail = $oContact->OtherEmail;
					}
					
					if (strlen($oContact->BirthdayYear) === 2)
					{
						$oDt = DateTime::createFromFormat('y', $oContact->BirthdayYear);
						$oContact->BirthdayYear = $oDt->format('Y');
					}					

					$iParsedCount++;
					$oContact->__SKIP_VALIDATE__ = true;

					if ($oAccount)
					{
						$oContact->IdDomain = $oAccount->IdDomain;
						$oContact->IdTenant = $oAccount->IdTenant;
					}
					$oContact->SharedToAll = $bIsShared;
					$oContact->GroupsIds = array($iGroupId);

					if ($this->oApiContactsManager->createContact($oContact))
					{
						$iCount++;
					}

					unset($oContact, $aParameters, $aCsvItem);
				}
			}
		}

		return $iCount;
	}
}
