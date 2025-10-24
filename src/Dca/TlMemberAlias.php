<?php

namespace Cm\MemberGoogleMapsBundle\Dca;

use Contao\Database;
use Contao\StringUtil;
use Contao\DataContainer;

class TlMemberAlias
{
    public function generateAlias($value, DataContainer $dc)
    {
        if ($value !== '') {
            return StringUtil::generateAlias($value);
        }

        // Build from firstname-lastname-city
        $row = Database::getInstance()->prepare('SELECT firstname,lastname,city FROM tl_member WHERE id=?')->limit(1)->execute($dc->id)->row();
        $parts = [];
        if (!empty($row['firstname'])) { $parts[] = $row['firstname']; }
        if (!empty($row['lastname']))  { $parts[] = $row['lastname']; }
        if (!empty($row['city']))      { $parts[] = $row['city']; }
        $base = implode('-', $parts);
        $alias = StringUtil::generateAlias($base ?: ('member-'.$dc->id));

        // Ensure unique
        $exists = Database::getInstance()->prepare('SELECT id FROM tl_member WHERE alias=? AND id<>?')->limit(1)->execute($alias, $dc->id);
        if ($exists->numRows) {
            $alias .= '-' . $dc->id;
        }
        return $alias;
    }
}

