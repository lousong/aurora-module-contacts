<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Contacts\Classes\VCard\Helper;
use Aurora\Modules\Contacts\Models\Group;

class Contact extends Model
{
	public $GroupsContacts = array();

	public $ExtendedInformation = array();

	protected $fillable = [
		'UUID',
		'IdUser',
		'IdTenant',
		'Storage',
		'FullName',
		'UseFriendlyName',
		'PrimaryEmail',
		'PrimaryPhone',
		'PrimaryAddress',
		'ViewEmail',

		'Title',
		'FirstName',
		'LastName',
		'NickName',
		'Skype',
		'Facebook',

		'PersonalEmail',
		'PersonalAddress',
		'PersonalCity',
		'PersonalState',
		'PersonalZip',
		'PersonalCountry',
		'PersonalWeb',
		'PersonalFax',
		'PersonalPhone',
		'PersonalMobile',

		'BusinessEmail',
		'BusinessCompany',
		'BusinessAddress',
		'BusinessCity',
		'BusinessState',
		'BusinessZip',
		'BusinessCountry',
		'BusinessJobTitle',
		'BusinessDepartment',
		'BusinessOffice',
		'BusinessPhone',
		'BusinessFax',
		'BusinessWeb',

		'OtherEmail',
		'Notes',

		'BirthDay',
		'BirthMonth',
		'BirthYear',

		'ETag',
		'Auto',
		'Frequency',
		'DateModified',
		'Properties'
	];

	protected $casts = [
        'Properties' => 'array',
    ];

	protected $appends = [
		'AgeScore'
	];

	public function getAgeScoreAttribute()
	{
		return 0;
	}

	/**
	 * Adds groups to contact. Groups are specified by names.
	 * @param array $aGroupNames List of group names.
	 */
	protected function addGroupsFromNames($aGroupNames)
	{
		$aNonExistingGroups = [];
		if (is_array($aGroupNames) && count($aGroupNames) > 0)
		{
			$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
			$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
			if ($oApiContactsManager)
			{
				foreach($aGroupNames as $sGroupName)
				{
					$oGroup = $oApiContactsManager->getGroups($this->IdUser, Group::firstWhere('Name', $sGroupName));
					if ($oGroup)
					{
						$this->Groups()->sync([$oGroup->Id], false);
					}

					// Group shouldn't be created here.
					// Very often after this populating contact will never be created.
					// It can be used only for suggestion to create.
					elseif (!empty($sGroupName))
					{
						$oGroup = new Group();
						$oGroup->IdUser = $this->IdUser;
						$oGroup->Name = $sGroupName;
						$aNonExistingGroups[] = $oGroup;
					}
				}
			}
		}

		return $aNonExistingGroups;
	}

	/**
	 * Add group to contact.
	 * @param string $sGroupUUID Group UUID.
	 */
	public function addGroups($aGroupUUIDs, $aGroupNames, $bCreateNonExistingGroups = false)
	{
		if (isset($aGroupUUIDs) && is_array($aGroupUUIDs)) {
			$this->Groups()->sync(Group::whereIn('UUID', $aGroupUUIDs)
				->get()->map(function ($oGroup) {
					return $oGroup->Id;
				}
			)->toArray());
		}
		$aNonExistingGroups = [];
		if (isset($aGroupNames))
		{
			$aNonExistingGroups = $this->addGroupsFromNames($aGroupNames);
		}

		if ($bCreateNonExistingGroups && is_array($aNonExistingGroups) && count($aNonExistingGroups) > 0) {
			$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
			$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
			if ($oApiContactsManager) {
				$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
				$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
				if ($oApiContactsManager) {
					$aGroupIds = [];
					foreach($aNonExistingGroups as $oGroup)
					{
						$oApiContactsManager->createGroup($oGroup);
						$aGroupIds[] = $oGroup->Id;
					}
					if (count($aGroupIds) > 0) {
						$this->Groups()->sync($aGroupIds, false);
					}
				}
			}
		}
	}

	/**
	 * Returns value of email that is specified as primary.
	 * @return string
	 */
	protected function getViewEmail()
	{
		switch ((int) $this->PrimaryEmail)
		{
			default:
			case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal:
				return (string) $this->PersonalEmail;
			case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business:
				return (string) $this->BusinessEmail;
			case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other:
				return (string) $this->OtherEmail;
		}
	}

	/**
	 * Sets ViewEmail field.
	 */
	public function SetViewEmail()
	{
		$this->ViewEmail = $this->getViewEmail();
	}

	/**
	 * Inits contacts from Vcard string.
	 * @param int $iUserId User identifier.
	 * @param string $sData Vcard string.
	 * @param string $sUid Contact UUID.
	 */
	public function InitFromVCardStr($iUserId, $sData, $sUid = '')
	{
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($iUserId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$this->IdUser = $oUser->EntityId;
			$this->IdTenant = $oUser->IdTenant;
		}

		if (!empty($sUid))
		{
			$this->UUID = $sUid;
		}

		$this->populate(
			Helper::GetContactDataFromVcard(
				\Sabre\VObject\Reader::read(
					$sData,
					\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
				)
			)
		);
	}

	/**
	 * Populate contact with specified data.
	 * @param array $aContact List of contact data.
	 */
	public function populate($aContact, $bCreateNonExistingGroups = false)
	{
		$aNonExistingGroups = [];
		parent::populate($aContact);

		if(!empty($aContact['UUID']))
		{
			$this->UUID = $aContact['UUID'];
		}
		else if(empty($this->UUID))
		{
			$this->UUID = \Sabre\DAV\UUIDUtil::getUUID();
		}
		$this->SetViewEmail();
	}


	/**
	 * Returns array with contact data.
	 * @return array
	 */
	public function toResponseArray()
	{
		$this->calculateETag();

		$aRes = parent::toResponseArray();

		$aRes['GroupUUIDs'] = $this->Groups->map(function ($oGroup) {
			return $oGroup->UUID;
		});

		foreach ($this->ExtendedInformation as $sKey => $mValue)
		{
			$aRes[$sKey] = $mValue;
		}

		$aArgs = ['Contact' => $this];
		\Aurora\System\Api::GetModule('Core')->broadcastEvent(
			'Contacts::Contact::ToResponseArray',
			$aArgs,
			$aRes
		);

		return $aRes;
	}

	public function calculateETag()
	{
		if (empty($this->ETag))
		{
			$this->ETag = \md5(\json_encode($this));
		}
	}

	public function Groups()
	{
		return $this->belongsToMany(Group::class, 'group_contact', 'ContactId', 'GroupId');
	}
}
