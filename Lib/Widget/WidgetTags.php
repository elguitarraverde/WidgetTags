<?php declare(strict_types=1);

namespace FacturaScripts\Plugins\WidgetTags\Lib\Widget;

use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Lib\Widget\BaseWidget;
use FacturaScripts\Dinamic\Model\CodeModel;
use Symfony\Component\HttpFoundation\Request;

class WidgetTags extends BaseWidget
{
    /** @var CodeModel */
    protected static $codeModel;

    /** @var string */
    protected $fieldcode;

    /** @var string */
    protected $fieldfilter;

    /** @var string */
    protected $fieldtitle;

    /** @var int */
    protected $limit;

    /** @var string */
    protected $parent;

    /** @var string */
    protected $source;

    /** @var bool */
    protected $translate;

    /** @var array */
    public $values = [];

    public function __construct(array $data)
    {
        if (!isset(static::$codeModel)) {
            static::$codeModel = new CodeModel();
        }

        parent::__construct($data);
        $this->parent = $data['parent'] ?? '';
        $this->translate = isset($data['translate']);

        foreach ($data['children'] as $child) {
            if ($child['tag'] !== 'values') {
                continue;
            }

            if (isset($child['source'])) {
                $this->setSourceData($child);
                break;
            } elseif (isset($child['start'])) {
                $this->setValuesFromRange($child['start'], $child['end'], $child['step']);
                break;
            }

            $this->setValuesFromArray($data['children'], $this->translate, !$this->required, 'text');
            break;
        }
    }

    /**
     * Obtains the configuration of the datasource used in obtaining data
     *
     * @return array
     */
    public function getDataSource(): array
    {
        return [
            'source' => $this->source,
            'fieldcode' => $this->fieldcode,
            'fieldfilter' => $this->fieldfilter,
            'fieldtitle' => $this->fieldtitle,
            'limit' => $this->limit,
        ];
    }

    /**
     * @param object $model
     * @param Request $request
     */
    public function processFormData(&$model, $request): void
    {
        $value = $request->request->get($this->fieldname, '');
        $model->{$this->fieldname} = ('' === $value) ? null : implode(',', $value);
    }

    /**
     * Loads the value list from a given array.
     * The array must have one of the two following structures:
     * - If it's a value array, it must use the value of each element as title and value
     * - If it's a multidimensional array, the indexes value and title must be set for each element
     *
     * @param array $items
     * @param bool $translate
     * @param bool $addEmpty
     * @param string $col1
     * @param string $col2
     */
    public function setValuesFromArray(array $items, bool $translate = false, bool $addEmpty = false, string $col1 = 'value', string $col2 = 'title'): void
    {
        foreach ($items as $item) {
            if (false === is_array($item)) {
                $this->values[] = ['value' => $item, 'title' => $item];
                continue;
            } elseif (isset($item['tag']) && $item['tag'] !== 'values') {
                continue;
            }

            if (isset($item[$col1])) {
                $this->values[] = [
                    'value' => $item[$col1],
                    'title' => $item[$col2] ?? $item[$col1],
                ];
            }
        }

        if ($translate) {
            $this->applyTranslations();
        }
    }

    public function setValuesFromArrayKeys(array $values, bool $translate = false, bool $addEmpty = false): void
    {
        foreach ($values as $key => $value) {
            $this->values[] = [
                'value' => $key,
                'title' => $value,
            ];
        }

        if ($translate) {
            $this->applyTranslations();
        }
    }

    /**
     * Loads the value list from an array with value and title (description)
     *
     * @param array $rows
     * @param bool $translate
     */
    public function setValuesFromCodeModel(array $rows, bool $translate = false): void
    {
        $this->values = [];
        foreach ($rows as $codeModel) {
            $this->values[] = [
                'value' => $codeModel->code,
                'title' => $codeModel->description,
            ];
        }

        if ($translate) {
            $this->applyTranslations();
        }
    }

    /**
     * @param int $start
     * @param int $end
     * @param int $step
     */
    public function setValuesFromRange(int $start, int $end, int $step): void
    {
        $values = range($start, $end, $step);
        $this->setValuesFromArray($values);
    }

    /** Translate the fixed titles, if they exist */
    private function applyTranslations(): void
    {
        foreach ($this->values as $key => $value) {
            if (empty($value['title'])) {
                continue;
            }

            $this->values[$key]['title'] = static::$i18n->trans($value['title']);
        }
    }

    protected function assets(): void
    {
        AssetManager::addJs(FS_ROUTE . '/Dinamic/Assets/JS/WidgetSelect.js');
        AssetManager::addCss('https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css');
        AssetManager::addJs('https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js');
    }

    /**
     * @param string $type
     * @param string $extraClass
     *
     * @return string
     */
    protected function inputHtml($type = 'text', $extraClass = '')
    {
        $class = $this->combineClasses($this->css('form-control'), $this->class, $extraClass);
        if ($this->parent) {
            $class = $class . ' parentSelect';
        }

        if ($this->readonly()) {
            return '<input type="hidden" name="' . $this->fieldname . '" value="' . $this->value . '"/>'
                . '<input type="text" value="' . $this->show() . '" class="' . $class . '" readonly/>';
        }

        $html = '<select multiple'
            . ' name="' . $this->fieldname . '[]"'
            . ' id="WidgetTagsInput-' . $this->id . '"'
            . ' class="' . $class . '"'
            . $this->inputHtmlExtraParams()
            . ' parent="' . $this->parent . '"'
            . ' value="' . $this->value . '"'
            . ' data-field="' . $this->fieldname . '"'
            . ' data-source="' . $this->source . '"'
            . ' data-fieldcode="' . $this->fieldcode . '"'
            . ' data-fieldtitle="' . $this->fieldtitle . '"'
            . ' data-fieldfilter="' . $this->fieldfilter . '"'
            . ' data-limit="' . $this->limit . '"'
            . '>';

        $html .= '
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    new Choices("#WidgetTagsInput-' . $this->id . '", {
                        removeItemButton: true,
                        searchResultLimit: -1,
                        fuseOptions: {
                          minMatchCharLength: 2
                        },                       
                    });
                });
            </script>';

        $found = false;
        foreach ($this->values as $option) {
            $title = empty($option['title']) ? $option['value'] : $option['title'];

            // don't use strict comparison (===)
            if (!empty($this->value) && in_array($option['value'], explode(',', $this->value))) {
                $found = true;
                $html .= '<option value="' . $option['value'] . '" selected>' . $title . '</option>';
                continue;
            }

            $html .= '<option value="' . $option['value'] . '">' . $title . '</option>';
        }

        // value not found?
        if (!$found && !empty($this->value) && !empty($this->source)) {
            $html .= '<option value="' . $this->value . '" selected>'
                . static::$codeModel->getDescription($this->source, $this->fieldcode, $this->value, $this->fieldtitle)
                . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Set datasource data and Load data from Model into values array.
     *
     * @param array $child
     * @param bool $loadData
     */
    protected function setSourceData(array $child, bool $loadData = true): void
    {
        $this->source = $child['source'];
        $this->fieldcode = $child['fieldcode'] ?? 'id';
        $this->fieldfilter = $child['fieldfilter'] ?? $this->fieldfilter;
        $this->fieldtitle = $child['fieldtitle'] ?? $this->fieldcode;
        $this->limit = $child['limit'] ?? CodeModel::ALL_LIMIT;
        if ($loadData && $this->source) {
            static::$codeModel::setLimit($this->limit);
            $values = static::$codeModel->all($this->source, $this->fieldcode, $this->fieldtitle, false);
            $this->setValuesFromCodeModel($values, $this->translate);
        }
    }

    /** @return string */
    protected function show()
    {
        if (null === $this->value) {
            return '-';
        }

        $selected = null;
        foreach ($this->values as $option) {
            // don't use strict comparation (===)
            if ($option['value'] == $this->value) {
                $selected = $option['title'];
            }
        }

        if (null === $selected) {
            // value is not in $this->values
            $selected = $this->source ?
                static::$codeModel->getDescription($this->source, $this->fieldcode, $this->value, $this->fieldtitle) :
                $this->value;

            $this->values[] = [
                'value' => $this->value,
                'title' => $selected,
            ];
        }

        return $selected;
    }
}
