<?php
/********************************************************************************
* Subs-LazyModerator.php - Subs of the Lazy Moderator Menu mod
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE,
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/**********************************************************************************
* Lazy Moderator Menu hooks
**********************************************************************************/
function LazyModerator_Verify_User()
{
	// Skip this if we are not requesting the layout of the moderator CPL:
	if (isset($_GET['action']) && $_GET['action'] == 'moderate' && isset($_GET['area']) && $_GET['area'] == 'lazymoderator_cpl')
		return isset($_GET['u']) ? (int) $_GET['u'] : 0;
}

function LazyModerator_Load_Theme()
{
	// These hooks must be last hook executed in their group!
	add_integration_function('integrate_moderation_areas', 'LazyModerator_Moderation_Hook', false);
	add_integration_function('integrate_menu_buttons', 'LazyModerator_Menu_Buttons', false);
}

function LazyModerator_Moderation_Hook(&$moderation_areas)
{
	global $scripturl, $modSettings, $context, $user_info, $context;

	// Skip this if we are not requesting the layout of the moderator CPL:
	if (empty($user_info['id']) || !isset($_GET['area']) || $_GET['area'] != 'lazymoderator_cpl' || empty($_GET['u']))
	{
		if (!empty($context['open_mod_reports']))
			$moderation_areas['posts']['areas']['reports']['label'] .= ' [<strong>' . $context['open_mod_reports'] . '</strong>]';
		return;
	}

	// Keep from triggering the Forum Hard Hit mod:
	if (!empty($context['HHP_time']))
		unset($_SESSION['HHP_Visits'][$context['HHP_time']]);
			
	// Rebuild the moderation menu:
	$cached = array();
	$outer_last = false;
	foreach ($moderation_areas as $id1 => $area1)
	{
		// Build first level menu:
		if (empty($area1['areas']))
			continue;
		$cached[$outer_last = $id1] = array(
			'title' => $area1['title'],
			'href' => $scripturl . '?action=moderate',
			'show' => false,
			'sub_buttons' => array(),
		);

		// Build second level menus:
		$first_shown = $last = $updated = false;
		foreach ($area1['areas'] as $id2 => $area2)
		{
			// No label?  Can't show the user that, then!
			$show = !isset($area2['enabled']) || !empty($area2['enabled']);
			if (!$show || empty($area2['label']))
				continue;
				
			// Add the area to the menu we are building:
			$link = isset($area2['custom_url']) ? $area2['custom_url'] : $scripturl . '?action=moderate;area=' . $id2;
			$cached[$id1]['sub_buttons'][$last = $id2] = array(
				'title' => $area2['label'],
				'href' => $link,
				'show' => $show,
			);
			if (!$updated)
				$updated = $cached[$id1]['href'] = $link;
			$first_shown = (!$first_shown ? $id2 : $first_shown);
			$cached[$id1]['show'] = true;
		}

		// Let's update the first level, just to make sure it's right:
		if (empty($first_shown))
			unset($cached[$id1]);
		elseif ($first_shown == $last)
			$cached[$id1] = $cached[$id1]['sub_buttons'][$first_shown];
		else
			$cached[$id1]['sub_buttons'][$last]['is_last'] = true;
	}
	$cached[$outer_last]['is_last'] = true;

	// Cache the built moderator menu:
	echo serialize($cached);
	exit;
}

function LazyModerator_Menu_Buttons(&$areas)
{
	global $txt, $scripturl, $user_info, $context;

	// Gotta prevent an infinite loop here:
	if (isset($_GET['action']) && $_GET['action'] == 'moderate' && isset($_GET['area']) && $_GET['area'] == 'lazymoderator_cpl')
		return;

	// Are we a guest, or can't see the moderation menu?  Then why bother with it?
	if (empty($user_info['id']) || empty($areas['moderate']['show']))
		return;

	// Attempt to get the cached moderator menu:
	if (($cached = cache_get_data('lazymoderator_' . $user_info['id'], 86400)) == null)
	{
		// Force the moderation code to build our new moderation menu:
		$contents = @file_get_contents($scripturl . '?action=moderate;area=lazymoderator_cpl;u=' . $user_info['id']);
		if (substr($contents, 0, 2) == 'a:')
		{
			$cached = @unserialize($contents);
			cache_put_data('lazymoderator_' . $user_info['id'], $cached, 86400);
		}
	}
	if (is_array($cached))
		$areas['moderate']['sub_buttons'] = $cached;
		
	// Add number of open reports to the moderation menu:
	$pending = $context['open_mod_reports'];
	if (!empty($context['open_mod_reports']))
		$areas['moderate']['title'] .= ' [<strong>' . $context['open_mod_reports'] . '</strong>]';

	// Add a total pending moderation issues to the top menu:
	if ($pending)
		$areas['moderate']['sub_buttons']['posts']['sub_buttons']['reports']['title'] .= ' [<strong>' . $pending . '</strong>]';
}

function LazyModerator_CoreFeatures(&$core_features)
{
	global $cachedir;
	if (isset($_POST['save']))
		array_map('unlink', glob($cachedir . '/data_*-SMF-lazymoderator_*'));
}

?>