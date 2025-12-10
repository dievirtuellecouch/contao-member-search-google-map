<?php

// Front end modules
$GLOBALS['FE_MOD']['user']['cm_membergooglemapsList'] = \Cm\MemberGoogleMapsBundle\Module\MemberGoogleMapsListModule::class;
$GLOBALS['FE_MOD']['user']['cm_membergooglemapsReader'] = \Cm\MemberGoogleMapsBundle\Module\MemberGoogleMapsReaderModule::class;
// Legacy finder module alias for search form
$GLOBALS['FE_MOD']['user']['cm_memberfinder'] = \Cm\MemberGoogleMapsBundle\Module\MemberGoogleMapsListModule::class;

// Back end widgets
$GLOBALS['BE_FFL']['cm_ListWizard'] = \Cm\MemberGoogleMapsBundle\Widget\ListSelectWizard::class;

// Ensure only one search field list appears in module configuration (run late via hook)
$GLOBALS['TL_HOOKS']['loadDataContainer'][] = [\Cm\MemberGoogleMapsBundle\Dca\DcaTweaks::class, 'onLoadDca'];

// Backend operations: coordinate maintenance
$GLOBALS['BE_MOD']['accounts']['member']['updCoords'] = [\Cm\MemberGoogleMapsBundle\Service\CoordsUpdater::class, 'handle'];
$GLOBALS['BE_MOD']['accounts']['member']['genCoords'] = [\Cm\MemberGoogleMapsBundle\Service\CoordsUpdater::class, 'handle'];
