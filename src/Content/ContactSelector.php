<?php
/**
 * @file src/Content/ContactSelector.php
 */
namespace Friendica\Content;

use Friendica\Core\Hook;
use Friendica\Core\L10n;
use Friendica\Core\Protocol;
use Friendica\Database\DBA;
use Friendica\Util\Network;
use Friendica\Util\Strings;

/**
 * @brief ContactSelector class
 */
class ContactSelector
{
	/**
	 * @param string $current     current
	 * @param string $foreign_net network
	 * @return string
	 * @throws \Exception
	 */
	public static function profileAssign($current, $foreign_net)
	{
		$o = '';

		$disabled = (($foreign_net) ? ' disabled="true" ' : '');

		$o .= "<select id=\"contact-profile-selector\" class=\"form-control\" $disabled name=\"profile-assign\" >\r\n";

		$s = DBA::select('profile', ['id', 'profile-name', 'is-default'], ['uid' => $_SESSION['uid']]);
		$r = DBA::toArray($s);

		if (DBA::isResult($r)) {
			foreach ($r as $rr) {
				$selected = (($rr['id'] == $current || ($current == 0 && $rr['is-default'] == 1)) ? " selected=\"selected\" " : "");
				$o .= "<option value=\"{$rr['id']}\" $selected >{$rr['profile-name']}</option>\r\n";
			}
		}
		$o .= "</select>\r\n";
		return $o;
	}

	/**
	 * @param string  $current  current
	 * @param boolean $disabled optional, default false
	 * @return object
	 */
	public static function pollInterval($current, $disabled = false)
	{
		$dis = (($disabled) ? ' disabled="disabled" ' : '');
		$o = '';
		$o .= "<select id=\"contact-poll-interval\" name=\"poll\" $dis />" . "\r\n";

		$rep = [
			0 => L10n::t('Frequently'),
			1 => L10n::t('Hourly'),
			2 => L10n::t('Twice daily'),
			3 => L10n::t('Daily'),
			4 => L10n::t('Weekly'),
			5 => L10n::t('Monthly')
		];

		foreach ($rep as $k => $v) {
			$selected = (($k == $current) ? " selected=\"selected\" " : "");
			$o .= "<option value=\"$k\" $selected >$v</option>\r\n";
		}
		$o .= "</select>\r\n";
		return $o;
	}

	/**
	 * @param string $profile Profile URL
	 * @return string Server URL
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	private static function getServerURLForProfile($profile)
	{
		$server_url = '';

		// Fetch the server url from the contact table
		$contact = DBA::selectFirst('contact', ['baseurl'], ['uid' => 0, 'nurl' => Strings::normaliseLink($profile)]);
		if (DBA::isResult($contact) && !empty($contact['baseurl'])) {
			$server_url = Strings::normaliseLink($contact['baseurl']);
		}

		if (empty($server_url)) {
			// Fetch the server url from the gcontact table
			$gcontact = DBA::selectFirst('gcontact', ['server_url'], ['nurl' => Strings::normaliseLink($profile)]);
			if (!empty($gcontact) && !empty($gcontact['server_url'])) {
				$server_url = Strings::normaliseLink($gcontact['server_url']);
			}
		}

		if (empty($server_url)) {
			// Create the server url out of the profile url
			$parts = parse_url($profile);
			unset($parts['path']);
			$server_url = Strings::normaliseLink(Network::unparseURL($parts));
		}

		return $server_url;
	}

	/**
	 * @param string $network network
	 * @param string $profile optional, default empty
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function networkToName($network, $profile = "")
	{
		$nets = [
			Protocol::DFRN      =>   L10n::t('DFRN'),
			Protocol::OSTATUS   =>   L10n::t('OStatus'),
			Protocol::FEED      =>   L10n::t('RSS/Atom'),
			Protocol::MAIL      =>   L10n::t('Email'),
			Protocol::DIASPORA  =>   L10n::t('Diaspora'),
			Protocol::ZOT       =>   L10n::t('Zot!'),
			Protocol::LINKEDIN  =>   L10n::t('LinkedIn'),
			Protocol::XMPP      =>   L10n::t('XMPP/IM'),
			Protocol::MYSPACE   =>   L10n::t('MySpace'),
			Protocol::GPLUS     =>   L10n::t('Google+'),
			Protocol::PUMPIO    =>   L10n::t('pump.io'),
			Protocol::TWITTER   =>   L10n::t('Twitter'),
			Protocol::DISCOURSE =>   L10n::t('Discourse'),
			Protocol::DIASPORA2 =>   L10n::t('Diaspora Connector'),
			Protocol::STATUSNET =>   L10n::t('GNU Social Connector'),
			Protocol::ACTIVITYPUB => L10n::t('ActivityPub'),
			Protocol::PNUT      =>   L10n::t('pnut'),
		];

		Hook::callAll('network_to_name', $nets);

		$search  = array_keys($nets);
		$replace = array_values($nets);

		$networkname = str_replace($search, $replace, $network);

		if ((in_array($network, Protocol::FEDERATED)) && ($profile != "")) {
			$server_url = self::getServerURLForProfile($profile);

			// Now query the GServer for the platform name
			$gserver = DBA::selectFirst('gserver', ['platform', 'network'], ['nurl' => $server_url]);

			if (DBA::isResult($gserver)) {
				if (!empty($gserver['platform'])) {
					$platform = $gserver['platform'];
				} elseif (!empty($gserver['network']) && ($gserver['network'] != Protocol::ACTIVITYPUB)) {
					$platform = self::networkToName($gserver['network']);
				}

				if (!empty($platform)) {
					$networkname = $platform;

					if ($network == Protocol::ACTIVITYPUB) {
						$networkname .= ' (AP)';
					}
				}
			}
		}

		return $networkname;
	}

	/**
	 * @param string $network network
	 * @param string $profile optional, default empty
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function networkToIcon($network, $profile = "")
	{
		$nets = [
			Protocol::DFRN      =>   'friendica',
			Protocol::OSTATUS   =>   'gnu-social', // There is no generic OStatus icon
			Protocol::FEED      =>   'rss',
			Protocol::MAIL      =>   'file-text-o', /// @todo
			Protocol::DIASPORA  =>   'diaspora',
			Protocol::ZOT       =>   'hubzilla',
			Protocol::LINKEDIN  =>   'linkedin',
			Protocol::XMPP      =>   'xmpp',
			Protocol::MYSPACE   =>   'file-text-o', /// @todo
			Protocol::GPLUS     =>   'google-plus',
			Protocol::PUMPIO    =>   'file-text-o', /// @todo
			Protocol::TWITTER   =>   'twitter',
			Protocol::DISCOURSE =>   'dot-circle-o', /// @todo
			Protocol::DIASPORA2 =>   'diaspora',
			Protocol::STATUSNET =>   'gnu-social',
			Protocol::ACTIVITYPUB => 'activitypub',
			Protocol::PNUT      =>   'file-text-o', /// @todo
		];

		$platform_icons = ['diaspora' => 'diaspora', 'friendica' => 'friendica', 'friendika' => 'friendica',
			'GNU Social' => 'gnu-social', 'gnusocial' => 'gnu-social', 'hubzilla' => 'hubzilla',
			'mastodon' => 'mastodon', 'peertube' => 'peertube', 'pixelfed' => 'pixelfed',
			'pleroma' => 'pleroma', 'red' => 'hubzilla', 'redmatrix' => 'hubzilla',
			'socialhome' => 'social-home', 'wordpress' => 'wordpress'];

		$search  = array_keys($nets);
		$replace = array_values($nets);

		$network_icon = str_replace($search, $replace, $network);

		if ((in_array($network, Protocol::FEDERATED)) && ($profile != "")) {
			$server_url = self::getServerURLForProfile($profile);

			// Now query the GServer for the platform name
			$gserver = DBA::selectFirst('gserver', ['platform'], ['nurl' => $server_url]);

			if (DBA::isResult($gserver) && !empty($gserver['platform'])) {
				$network_icon = $platform_icons[strtolower($gserver['platform'])] ?? $network_icon;
			}
		}

		return $network_icon;
	}

	/**
	 * @param string $current optional, default empty
	 * @param string $suffix  optionsl, default empty
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function gender($current = "", $suffix = "")
	{
		$o = '';
		$select = ['', L10n::t('Male'), L10n::t('Female')];
		Hook::callAll('gender_selector', $select);

		$o .= "<select name=\"gender$suffix\" id=\"gender-select$suffix\" size=\"1\" >";
		foreach ($select as $neutral => $selection) {
			if ($selection !== 'NOTRANSLATION') {
				$selected = (($neutral == $current) ? ' selected="selected" ' : '');
				$o .= "<option value=\"$neutral\" $selected >$selection</option>";
			}
		}
		$o .= '</select>';
		return $o;
	}

	/**
	 * @param string $current optional, default empty
	 * @param string $suffix  optionsl, default empty
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function sexualPreference($current = "", $suffix = "")
	{
		$o = '';
		$select = ['', L10n::t('Males'), L10n::t('Females')];

		Hook::callAll('sexpref_selector', $select);

		$o .= "<select name=\"sexual$suffix\" id=\"sexual-select$suffix\" size=\"1\" >";
		foreach ($select as $neutral => $selection) {
			if ($selection !== 'NOTRANSLATION') {
				$selected = (($neutral == $current) ? ' selected="selected" ' : '');
				$o .= "<option value=\"$neutral\" $selected >$selection</option>";
			}
		}
		$o .= '</select>';
		return $o;
	}

	/**
	 * @param string $current optional, default empty
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function maritalStatus($current = "")
	{
		$o = '';
		$select = ['', L10n::t('Single'), L10n::t('Unavailable'), L10n::t('Engaged'), L10n::t('Married'), L10n::t('Don\'t care'), L10n::t('Ask me')];

		Hook::callAll('marital_selector', $select);

		$o .= '<select name="marital" id="marital-select" size="1" >';
		foreach ($select as $neutral => $selection) {
			if ($selection !== 'NOTRANSLATION') {
				$selected = (($neutral == $current) ? ' selected="selected" ' : '');
				$o .= "<option value=\"$neutral\" $selected >$selection</option>";
			}
		}
		$o .= '</select>';
		return $o;
	}
}
