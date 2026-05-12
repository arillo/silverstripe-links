<?php
namespace Arillo\Links;

use SilverStripe\Core\Extension;

/**
 * Attaches a has one Link relation
 *
 * @package Arillo\Links
 * @author bumbus <sf@arillo.net>
 */
class LinkExtension extends Extension
{
    const FIELD = 'LinkObject';

    private static $has_one = [
        self::FIELD => Link::class,
    ];

    private static $cascade_duplicates = [ self::FIELD ];

    private static $owns = [self::FIELD];

    protected function onBeforeWrite()
    {
        Link::write_prefixed($this->owner);
    }

    protected function onBeforeDelete()
    {
        $link = $this->owner->{self::FIELD}();

        if ($link->exists()) {
            $link->delete();
        }
    }

}
