# Arillo\Links

[![Latest Stable Version](https://poser.pugx.org/arillo/silverstripe-shortpixel/v/stable?format=flat)](https://packagist.org/packages/arillo/silverstripe-links)
&nbsp;
[![Total Downloads](https://poser.pugx.org/arillo/silverstripe-shortpixel/downloads?format=flat)](https://packagist.org/packages/arillo/silverstripe-links)

Add links to any DataObject.

### Requirements

SilverStripe CMS ^4.0

## Installation

```bash
composer require arillo/silverstripe-links
```

## Usage

Attach the `Arillo\Links\LinkExtension` to your DataObject via `config.yml`:

```
MyDataObject:
  extensions:
    - Arillo\Links\LinkExtension
```

```php
use SilverStripe\ORM\DataObject;
use Arillo\Links\Link;

class MyDataObject extends DataObject
{
    public function getCMSFields()
      {
          $this->beforeUpdateCMSFields(function($fields) {
              $fields->addFieldsToTab(
                  'Root.Main',
                  // add link fields directly to the belonging DataObject.
                  Link::edit_fields(
                      $this,
                      [
                          'mode' => Link::EDITMODE_PLAIN,
                          'showLinkTitle' => true,
                      ]
                  )

                  // or use default editing via HasOneButtonField
                  Link::edit_fields($this)
              );
          });
          return parent::getCMSFields();
      }
}
```

Inspect `Arillo\Links::DEFAULT_FIELDS_CONFIG` for all available config keys.

In links can be rendered in templates like this:

```
<% if $Link.Exists %>
  <% with $Link %>
    <a href="$Href" $TargetAttr.RAW>$Title</a>
  <% end_with %>
<% end_if %>
```

or use the template of the module:

```
<% include Link Link=$LinkObject, CssClass=btn btn-primary %>
```
