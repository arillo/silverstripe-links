<?php
namespace Arillo\Links;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Config\Config;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Email\Email;

use SilverStripe\Forms\{
    TextField,
    FieldList,
    TreeDropdownField,
    DropdownField,
    CheckboxField,
    TabSet
};

use UncleCheese\DisplayLogic\Forms\Wrapper;
use SilverShop\HasOneField\HasOneButtonField;

use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

/**
 * Data model representing a link.
 *
 * @package Arillo\Links
 * @author bumbus <sf@arillo.net>
 */
class Link extends DataObject
{
    /**
     * Flag for cms fields generation.
     * Indicates that link cms fields will be added to parent cms fields.
     */
    const EDITMODE_PLAIN = 'EDITMODE_PLAIN';

    /**
     * Flag for cms fields generation.
     * Indicates that fields will be managed via HasOneButtonField.
     */
    const EDITMODE_NESTED = 'EDITMODE_NESTED';

    /**
     * In EDITMODE_PLAIN cms fields will be prefixed by this.
     */
    const PLAINMODE_PREFIX = 'AOLink_';

    /**
     * Default config for cms fields generation.
     */
    const DEFAULT_FIELDS_CONFIG = [
        'field' => LinkExtension::FIELD, // link field name in paren object
        'mode' => self::EDITMODE_NESTED, // EDITMODE_PLAIN | EDITMODE_NESTED
        'showLinkTitle' => true, // show link title in cms
        'fieldsPrefix' => null, // fields prefix used in EDITMODE_PLAIN
    ];

    private static
        $table_name = 'Arillo_Link',

        $db = [
            'Title' => 'Varchar(255)',
            'URL' => 'Varchar(255)',
            'Email' => 'Varchar(255)',
            'Type' => 'Varchar(255)',
            'External' => 'Boolean',
        ],

        $has_one = [
            'Page' => SiteTree::class,
        ],

        $email_obfuscation_method = 'hex', // hex, visible, direction

        $link_types = [
            'none',
            'internal',
            'external',
            'email'
        ],

        $searchable_fields = [
            'Title',
            'URL',
        ]
    ;

    /**
     * Maps link field names with prefixed link field names.
     * @return array
     */
    public static function map_prefix_link_fields()
    {
        $fields = array_keys(Config::inst()->get(__CLASS__, 'db'));
        $fields = array_merge(
            $fields,
            array_map(
                function($f) { return "{$f}ID"; },
                array_keys(Config::inst()->get(__CLASS__, 'has_one'))
            )
        );

        $linkFields = [];

        foreach ($fields as $field)
        {
            $linkFields[$field] = self::PLAINMODE_PREFIX . $field;
        }

        return $linkFields;
    }

    /**
     * Writes prefixed fields into the related link object.
     *
     * @param  DataObject $owner
     * @return DataObject
     */
    public static function write_prefixed(DataObject $owner)
    {
        $fields = self::map_prefix_link_fields();
        $link = $owner->{LinkExtension::FIELD}();

        if (
            $owner->{self::PLAINMODE_PREFIX . 'Type'}
        ) {
            if (
                $owner->{self::PLAINMODE_PREFIX . 'Type'} == 'none'
                && $link->exists()
            ) {
                $link->delete();
                $owner->{LinkExtension::FIELD . 'ID'} = 0;
                return $owner;
            }

            foreach ($fields as $field => $prefixedField)
            {
                $link->{$field} = $owner->{$prefixedField};
            }

            $link->write();

            $owner->{LinkExtension::FIELD . 'ID'} = $link->ID;
        }

        return $owner;
    }

    /**
     * Merges custom field config with default config.
     * @param  array $config
     * @return array
     */
    public static function fields_config(array $config = [])
    {
        return array_merge(self::DEFAULT_FIELDS_CONFIG, $config);
    }

    /**
     * Edit fields (shortcut) for cms usage.
     * @param  DataObject $record
     * @param  array      $config
     * @return array
     */
    public static function edit_fields(
        DataObject $record,
        array $config = []
    ) {
        $fields = [];

        $config = self::fields_config($config);

        switch ($config['mode'])
        {
            case self::EDITMODE_NESTED:
                if ($record->exists())
                {
                    $link = HasOneButtonField::create(
                        $record,
                        $config['field'],
                        null,
                        _t(__CLASS__ . '.Link', 'Link')
                    );

                    $link
                        ->setModelClass(__CLASS__)
                        ->getConfig()
                        ->removeComponentsByType(
                            GridFieldAddExistingAutocompleter::class
                        )
                        ->addComponent(new GridFieldDeleteAction())
                    ;

                    $fields[] = $link;
                }
                break;

            case self::EDITMODE_PLAIN:
                $fields = self::cms_fields(
                    $record,
                    array_merge($config, [
                        'fieldsPrefix' => $config['fieldsPrefix'] ?? Link::PLAINMODE_PREFIX
                    ])
                );
                break;
        }

        return $fields;
    }

    /**
     * Fields with display logic.
     *
     * @param  bool|boolean $showLinkTitle
     * @param  string       $fieldsPrefix
     * @return array
     */
    public static function cms_fields(
        DataObject $record,
        array $config = []
    ) {
        $config = self::fields_config($config);

        $fieldsPrefix = $config['fieldsPrefix'];

        if (
            $fieldsPrefix
            && $record->{LinkExtension::FIELD . 'ID' > 0}
        ) {
            $link = $record->{LinkExtension::FIELD}();
            $linkFields = self::map_prefix_link_fields();
            foreach ($linkFields as $field => $prefixedField)
            {
                $record->{$prefixedField} = $link->{$field};
            }
        }

        $types = Config::inst()->get(__CLASS__, 'link_types');

        $typesMap = [];

        foreach ($types as $type)
        {
            $typesMap[$type] = _t(__CLASS__ . ".Type_{$type}", $type);
        }

        $fields = [
            DropdownField::create(
                "{$fieldsPrefix}Type",
                _t(__CLASS__ . '.Type', 'Link type'),
                $typesMap
            ),

            CheckboxField::create(
                "{$fieldsPrefix}External",
                _t(__CLASS__ . '.External', 'Open link in new tab?')
            )
                ->displayIf("{$fieldsPrefix}Type")
                ->isNotEqualTo('none')
                ->andIf("{$fieldsPrefix}Type")
                ->isNotEqualTo('email')
                ->end()
        ];

        if ($config['showLinkTitle'])
        {
            $fields[] = TextField::create(
                "{$fieldsPrefix}Title",
                'Link title'
            )
                ->displayIf("{$fieldsPrefix}Type")
                ->isNotEqualTo('none')
                ->andIf("{$fieldsPrefix}Type")
                ->isNotEqualTo('email')
                ->end()
            ;
        }

        array_push(
            $fields,
            TextField::create(
                "{$fieldsPrefix}URL",
                _t(__CLASS__ . '.URL', 'Url')
            )
                ->displayIf("{$fieldsPrefix}Type")
                ->isEqualTo('external')
                ->end()
            ,
            TextField::create(
                "{$fieldsPrefix}Email",
                _t(__CLASS__ . '.Email', 'Email-Address')
            )
                ->displayIf("{$fieldsPrefix}Type")
                ->isEqualTo('email')
                ->end()
            ,

            Wrapper::create(
                TreeDropdownField::create(
                    "{$fieldsPrefix}PageID",
                    _t(__CLASS__ . '.Page', 'Page'),
                    SiteTree::class
                )
            )
                ->setName('LinkPageWrapper')
                ->displayIf("{$fieldsPrefix}Type")
                ->isEqualTo('internal')
                ->end()
        );

        $fluentClass = 'TractorCow\Fluent\Extension\FluentExtension';

        if (
            $record->hasExtension($fluentClass)
            && $translate = Config::inst()->get(__CLASS__, 'translate')
        ) {

            foreach ($fields as $field)
            {
                if (
                    !in_array(ltrim($field->ID(), self::PLAINMODE_PREFIX), $translate)
                    || $field->hasClass('fluent__localised-field')
                ) {
                    continue;
                }

                $translatedTooltipTitle = _t(
                    "{$fluentClass}.FLUENT_ICON_TOOLTIP",
                    'Translatable field'
                );

                $tooltip = DBField::create_field(
                    'HTMLFragment',
                    "<span class='font-icon-translatable' title='$translatedTooltipTitle'></span>"
                );

                $field->addExtraClass('fluent__localised-field');
                $field->setTitle(
                    DBField::create_field('HTMLFragment', $tooltip . $field->Title())
                );
            }
        }

        return $fields;
    }

    /**
     * Generates an href from a Link.
     *
     * @param  Link $record
     * @return string|null
     */
    public static function href_for(Link $record)
    {
        switch (true)
        {
            case $record->Type == 'external' && $record->URL:
                return $record->URL;

            case $record->Type == 'internal':
                $page = $record->Page();
                if ($page->exists()) return $page->Link();
                break;

            case $record->Type == 'email' && $record->Email:
                $email = Email::obfuscate(
                    $record->Email,
                    Config::inst()->get(__CLASS__, 'email_obfuscation_method')
                );
                return "mailto:{$email}";

            default: return null;
        }
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));
        $fields->addFieldsToTab(
            'Root.Main',
            self::cms_fields($this)
        );

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Link target attribute for template use.
     * Call $TargetAttr.RAW to avoid escaping.
     *
     * @return string|null
     */
    public function getTargetAttr()
    {
        if ($this->External) return 'target="_blank" rel="noopener noreferrer"';

        return null;
    }

    /**
     * @return string
     */
    public function getHref()
    {
        $link = static::href_for($this);

        $extendedLink = $this->extend('updateHref', $link);
        if (isset($extendedLink) && count($extendedLink)) return $extendedLink[0];

        return $link;
    }
}
