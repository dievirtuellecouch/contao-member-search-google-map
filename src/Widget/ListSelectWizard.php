<?php

namespace Cm\MemberGoogleMapsBundle\Widget;

use Contao\Controller;
use Contao\Input;
use Contao\StringUtil;
use Contao\Widget;

/**
 * Simple select-based list wizard with add/remove/reorder controls.
 * Stores value as serialized array of rows: [['field' => 'company'], ...]
 */
class ListSelectWizard extends Widget
{
    protected $blnSubmitInput = true;
    protected $strTemplate = 'be_widget';

    public function __construct($arrAttributes=null)
    {
        parent::__construct($arrAttributes);
        // Normalize value
        $this->varValue = StringUtil::deserialize($this->varValue, true);
        if (!\is_array($this->varValue)) { $this->varValue = []; }
    }

    public function validate(): void
    {
        // Handle GET-based immediate commands (replicates legacy behaviour)
        $strCommand = 'cmd_' . $this->strField;
        $cmd = Input::get($strCommand);
        $cid = Input::get('cid');
        if ($cmd && is_numeric($cid) && (int)Input::get('id') === (int)$this->currentRecord) {
            $idx = (int)$cid;
            $rows = array_values($this->varValue);
            switch ($cmd) {
                case 'up':
                    if ($idx>0) { $tmp=$rows[$idx-1]; $rows[$idx-1]=$rows[$idx]; $rows[$idx]=$tmp; }
                    break;
                case 'down':
                    if ($idx < count($rows)-1) { $tmp=$rows[$idx+1]; $rows[$idx+1]=$rows[$idx]; $rows[$idx]=$tmp; }
                    break;
                case 'delete':
                    unset($rows[$idx]);
                    $rows = array_values($rows);
                    break;
                case 'copy':
                    $copy = $rows[$idx] ?? ['field' => ''];
                    array_splice($rows, $idx+1, 0, [$copy]);
                    break;
                case 'new':
                    array_splice($rows, $idx+1, 0, [['field' => '']]);
                    break;
            }
            $this->varValue = $rows;
            // Persist change immediately
            try {
                \Contao\Database::getInstance()
                    ->prepare('UPDATE ' . $this->strTable . ' SET ' . $this->strField . '=? WHERE id=?')
                    ->execute(serialize($this->varValue), $this->currentRecord);
            } catch (\Throwable $e) {}
            // Redirect to clean URL
            $url = \Contao\Environment::get('request');
            $url = preg_replace('~&(amp;)?cid=[^&]*~i', '', $url);
            $url = preg_replace('~&(amp;)?'.preg_quote($strCommand,'~').'=[^&]*~i', '', $url);
            \Contao\Controller::redirect($url);
        }

        // Handle posted rows (on regular save)
        $posted = Input::post($this->strName);
        if (\is_array($posted)) {
            $rows=[];
            foreach ($posted as $r) {
                $rows[] = ['field' => (string)($r['field'] ?? '')];
            }
            $this->varValue = $rows;
        }

        parent::validate();
        $this->varValue = serialize($this->varValue);
    }

    public function generate(): string
    {
        // Handle immediate GET-based operations (like legacy Contao 3.5)
        $strCommand = 'cmd_' . $this->strField;
        $cmd = Input::get($strCommand);
        $cid = Input::get('cid');
        if ($cmd && is_numeric($cid) && (int)Input::get('id') === (int)$this->currentRecord) {
            $idx = (int)$cid;
            $rows = array_values(StringUtil::deserialize($this->value, true));
            switch ($cmd) {
                case 'up':
                    if ($idx>0) { $tmp=$rows[$idx-1]; $rows[$idx-1]=$rows[$idx]; $rows[$idx]=$tmp; }
                    break;
                case 'down':
                    if ($idx < count($rows)-1) { $tmp=$rows[$idx+1]; $rows[$idx+1]=$rows[$idx]; $rows[$idx]=$tmp; }
                    break;
                case 'delete':
                    unset($rows[$idx]);
                    $rows = array_values($rows);
                    break;
                case 'copy':
                    $copy = $rows[$idx] ?? ['field' => ''];
                    array_splice($rows, $idx+1, 0, [$copy]);
                    break;
                case 'new':
                    array_splice($rows, $idx+1, 0, [['field' => '']]);
                    break;
            }
            try {
                \Contao\Database::getInstance()
                    ->prepare('UPDATE ' . $this->strTable . ' SET ' . $this->strField . '=? WHERE id=?')
                    ->execute(serialize($rows), $this->currentRecord);
            } catch (\Throwable $e) {}
            // Redirect to clean URL (removing command parameters)
            $url = \Contao\Environment::get('request');
            $url = preg_replace('~&(amp;)?cid=[^&]*~i', '', $url);
            $url = preg_replace('~&(amp;)?'.preg_quote($strCommand,'~').'=[^&]*~i', '', $url);
            \Contao\Controller::redirect($url);
        }

        // Resolve options (value=>label)
        $options = $this->getFieldOptions();

        $rows = StringUtil::deserialize($this->value, true);
        if (!\is_array($rows)) { $rows = []; }
        if (!$rows) { $rows = [['field' => '']]; }

        $buffer = '<div class="tl_listwizard cm-listselect">'
                .'<table class="tl_module" style="width:auto">'
                .'<tbody>';
        $cmdBase = 'cmd_'.$this->strField;
        $rid = (int) $this->currentRecord;
        foreach (array_values($rows) as $i => $row) {
            $cur = (string)($row['field'] ?? '');
            $buffer .= '<tr>'
                    .'<td>'
                    .'<select name="'.$this->strName.'['.$i.'][field]" class="tl_select tl_chosen" style="min-width:22rem">';
            $buffer .= '<option value="">- auswählen -</option>';
            foreach ($options as $val => $label) {
                $sel = ($val === $cur) ? ' selected' : '';
                $buffer .= '<option value="'.StringUtil::specialchars($val).'"'.$sel.'>'.StringUtil::specialchars($label).'</option>';
            }
            $buffer .= '</select>'
                    .'</td>'
                    .'<td class="operations" style="white-space:nowrap">'
                    .$this->opLink($cmdBase,'up',$i,$rid,'nach oben')
                    .$this->opLink($cmdBase,'down',$i,$rid,'nach unten')
                    .$this->opLink($cmdBase,'copy',$i,$rid,'duplizieren')
                    .$this->opLink($cmdBase,'new',$i,$rid,'neue Zeile')
                    .$this->opLink($cmdBase,'delete',$i,$rid,'löschen')
                    .'</td>'
                    .'</tr>';
        }
        $buffer .= '</tbody></table></div>';
        return $buffer;
    }

    protected function opLink(string $cmdBase, string $action, int $idx, int $rid, string $title): string
    {
        $url = \Contao\Controller::addToUrl('&'.$cmdBase.'='.$action.'&cid='.$idx.'&id='.$rid);
        $icon = ['up'=>'▲','down'=>'▼','copy'=>'⧉','new'=>'+','delete'=>'✖'][$action] ?? $action;
        return '<a href="'.$url.'" class="tl_submit" title="'.StringUtil::specialchars($title).'" style="margin:0 .15rem">'.$icon.'</a>';
    }

    private function getFieldOptions(): array
    {
        // Options directly on field (may be in Contao's structured format)
        $options = $this->options ?? [];
        // options_callback on DCA field
        if (!$options && isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['options_callback'])) {
            $cb = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['options_callback'];
            if (\is_array($cb)) {
                $class = $cb[0]; $method = $cb[1];
                if (\is_string($class) && \method_exists($class, $method)) {
                    $options = (array) $class::$method();
                } elseif (\is_object($class) && \method_exists($class, $method)) {
                    $options = (array) $class->$method();
                }
            } elseif (\is_callable($cb)) {
                $options = (array) ($cb)();
            }
        }
        // Normalize into [value => label]
        $options = $this->normalizeOptions($options);

        // Fallback: tl_member fields with labels
        if (!$options) {
            Controller::loadDataContainer('tl_member');
            Controller::loadLanguageFile('tl_member');
            foreach ($GLOBALS['TL_DCA']['tl_member']['fields'] as $k => $def) {
                $lab = $def['label'][0] ?? ucfirst($k);
                $options[$k] = $lab;
            }
        }
        return $options;
    }

    private function normalizeOptions(array $options): array
    {
        $norm = [];
        if (!$options) { return $norm; }
        // Handle associative map (value => label)
        $isAssoc = array_keys($options) !== range(0, count($options) - 1);
        if ($isAssoc) {
            foreach ($options as $val => $label) {
                if (\is_array($label)) {
                    // Contao often provides label as [text, desc]
                    $label = $label[0] ?? reset($label) ?? (string)$val;
                }
                $norm[(string)$val] = (string)$label;
            }
            return $norm;
        }
        // Handle numeric list of option arrays or groups
        foreach ($options as $opt) {
            if (\is_array($opt)) {
                if (isset($opt['value'])) {
                    $label = $opt['label'] ?? $opt['value'];
                    if (\is_array($label)) { $label = $label[0] ?? reset($label) ?? $opt['value']; }
                    $norm[(string)$opt['value']] = (string)$label;
                } elseif (isset($opt['group'])) {
                    // Grouped options: ['group' => 'Name', 'options' => [ ['value'=>..,'label'=>..], ... ]]
                    $sub = $opt['options'] ?? [];
                    foreach ($sub as $s) {
                        if (!\is_array($s) || !isset($s['value'])) { continue; }
                        $label = $s['label'] ?? $s['value'];
                        if (\is_array($label)) { $label = $label[0] ?? reset($label) ?? $s['value']; }
                        $norm[(string)$s['value']] = (string)$label;
                    }
                }
            }
        }
        return $norm;
    }
}
