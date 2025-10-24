<?php

namespace Cm\MemberGoogleMapsBundle\ContaoManager;

use Cm\MemberGoogleMapsBundle\CmMemberGoogleMapsBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(CmMemberGoogleMapsBundle::class)
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle'])
        ];
    }
}

