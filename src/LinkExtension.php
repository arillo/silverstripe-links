<?php
namespace Arillo\Links;

use SilverStripe\ORM\DataExtension;

/**
 * Attaches a has one Link relation
 *
 * @package Arillo\Links
 * @author bumbus <sf@arillo.net>
 */
class LinkExtension extends DataExtension
{
    const FIELD = 'LinkObject';

    private static $has_one = [
        self::FIELD => Link::class,
    ];

    private static $owns = [self::FIELD];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        Link::write_prefixed($this->owner);
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $link = $this->owner->{self::FIELD}();

        if ($link->exists()) {
            $link->delete();
        }
    }
}
