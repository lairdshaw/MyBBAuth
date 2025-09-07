<?php

/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*  Version 1.0.0
 */

/**
 * For now, only MySQL/MariaDB is supported.
 */

namespace MyBBAuth\Auth;

use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PrimaryAuthenticationProvider;
use MediaWiki\MediaWikiServices;
use StatusValue;
use User;

class MyBBAuthenticationProvider extends AbstractPasswordPrimaryAuthenticationProvider {
	protected $forum_path;
	protected $table_prefix;
	protected $mybb_conf;
	protected $db;

	/**
	 * @var array An array of user group IDs for banned user groups (defaults to the MyBB banned group)
	 */
	protected $banned_usergroups = array(7);

	/**
	 * @var array An array of group IDs which should have SysOp/Administrative access to the wiki (defaults to Super Moderators and Administrators)
	 */
	protected $admin_usergroups = array(4,3);

	public function __construct(array $params = []) {
		global $wgMyBBAuthForumPath;

		parent::__construct($params);

		$this->forum_path = $wgMyBBAuthForumPath;
		require_once $this->forum_path."inc/config.php";
		$this->table_prefix = $config['database']['table_prefix'];
		$this->mybb_conf = $config;
	}

	public function beginPrimaryAuthentication(array $reqs) {
		$req = AuthenticationRequest::getRequestByClass($reqs, MyBBAuthenticationRequest::class);
		if (!$req) {
			return AuthenticationResponse::newFail(
				wfMessage('mybbauth-unexpected-error')
			);
		}	

		$query = mysqli_query($this->getDb(), "
			SELECT username, password, salt, usergroup
			FROM {$this->table_prefix}users
			WHERE REPLACE(username, ' ', '_')='".mysqli_escape_string($this->getDb(), str_replace(' ', '_', $req->username))."'
			ORDER BY uid ASC
			LIMIT 1"
		);
		$mybb_user = mysqli_fetch_assoc($query);
		if ($mybb_user) {
			$saltedpw = md5(md5($mybb_user['salt']).md5($req->password));
			if (!empty($mybb_user['username']) && $mybb_user['password'] == $saltedpw && !in_array($mybb_user['usergroup'], $this->banned_usergroups)) {
				return AuthenticationResponse::newPass($req->username);
			}
		}

		return AuthenticationResponse::newFail(
			wfMessage('wrongpassword')
		);
	}

	public function testUserExists($username, $flags = User::READ_NORMAL) {
		$query = mysqli_query($this->getDb(), "
			SELECT username
			FROM {$this->table_prefix}users
			WHERE REPLACE(username, ' ', '_')='".mysqli_escape_string($this->getDb(), str_replace(' ', '_',  $username))."'
			ORDER BY uid ASC
			LIMIT 1"
		);
		$user  = mysqli_fetch_assoc($query);

		return !empty($user['username']);
	}

	public function onUserLoggedIn($user) {
		$query = mysqli_query($this->getDb(), "
			SELECT username, email, usergroup, additionalgroups
			FROM {$this->table_prefix}users
			WHERE REPLACE(username, ' ', '_')='".mysqli_escape_string($this->db, str_replace(' ', '_', $user->mName))."'
			ORDER BY uid ASC
			LIMIT 1"
		);
		if (($mybb_user = mysqli_fetch_array($query))) {
			// If necessary, update the user's sysop status based on
			// potentially changed MyBB group memberships.
			$is_admin = in_array($mybb_user['usergroup'], $this->admin_usergroups);
			if (!$is_admin) {
				foreach (explode(',', $mybb_user['additionalgroups']) as $group) {
					if (in_array($group, $this->admin_usergroups)) {
						$is_admin = true;
						break;
					}
				}
			}
			$effGroups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups($user);
			if ($is_admin) {
				// If the user is not a sysop but should be, then make him/her a sysop.
				if (!in_array('sysop', $effGroups)) {
					$user->addGroup('sysop');
				}
			} else if (in_array('sysop', $effGroups)) {
				// Or if the user is a sysop but shouldn't be, then demote him/her from sysop.
				$user->removeGroup('sysop');
			}

			$user->setEmail($mybb_user['email']);
			$user->setRealName($mybb_user['username']);

			$user->saveSettings();
		}
	}
	
	public function providerAllowsAuthenticationDataChange(AuthenticationRequest $req, $checkData = true) {
		return StatusValue::newGood('ignored');
	}

	public function providerChangeAuthenticationData(AuthenticationRequest $req) {}

	public function accountCreationType() {
		return PrimaryAuthenticationProvider::TYPE_NONE;
	}

	public function beginPrimaryAccountCreation($user, $creator, array $reqs) {
		return AuthenticationResponse::FAIL;
	}

	public function getAuthenticationRequests($action, array $options) {
		switch ($action) {
		case AuthManager::ACTION_LOGIN:
			return [new MyBBAuthenticationRequest()];
		default:
			return [];
		}
	}

	/**
	 * Connects to the DB if not connected already.
	 * @return object The connection (if any) to the MyBB database on its MySQL server.
	 */
 	protected function getDb() {
		if (!$this->db) {
			($this->db = mysqli_connect(
				$this->mybb_conf['database']['hostname'],
				$this->mybb_conf['database']['username'],
				$this->mybb_conf['database']['password'],
				$this->mybb_conf['database']['database']
			)) or die("Unable to connect to the MyBB database.");
		}

 		return $this->db;
 	}
}
