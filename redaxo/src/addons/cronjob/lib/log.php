<?php

/**
 * Cronjob Addon
 *
 * @author gharlan[at]web[dot]de Gregor Harlan
 *
 * @package redaxo\cronjob
 */

class rex_cronjob_log
{
    public static function getYears()
    {
        $folder = REX_CRONJOB_LOG_FOLDER;
        $years = [];

        if (is_dir($folder)) {
            foreach (rex_finder::factory($folder)->dirsOnly()->sort() as $file) {
                $years[] = $file->getFilename();
            }
        }

        return $years;
    }

    public static function getMonths($year)
    {
        $folder = REX_CRONJOB_LOG_FOLDER;
        $months = [];
        foreach (glob($folder . $year . '/' . $year . '-*.log') as $file) {
            $month = substr($file, -6, 2);
            $months[] = $month;
        }
        return $months;
    }

    public static function getYearMonthArray()
    {
        $array = [];
        foreach (self::getYears() as $year) {
            $months = self::getMonths($year);
            if (!empty($months)) {
                $array[$year] = $months;
            }
        }
        return $array;
    }

    public static function getLogOfMonth($month, $year)
    {
        $file = REX_CRONJOB_LOG_FOLDER . $year . '/' . $year . '-' . $month . '.log';
        return rex_file::get($file);
    }

    public static function getListOfMonth($month, $year)
    {
        $lines = explode("\n", trim(self::getLogOfMonth($month, $year)));
        $monthName = rex_formatter::strftime(mktime(0, 0, 0, $month, 1, 1), '%B');
        $caption = rex_i18n::msg('cronjob_log_caption_1', $monthName, $year);
        return self::_getList($lines, $caption);
    }

    public static function getListOfNewestMessages($limit = 10)
    {
        $array = array_reverse(self::getYearMonthArray(), true);
        $messages = [];
        foreach ($array as $year => $months) {
            $months = array_reverse($months, true);
            foreach ($months as $month) {
                $lines = explode("\n", trim(self::getLogOfMonth($month, $year)));

                $end = min($limit - count($messages), count($lines));
                for ($i = 0; $i < $end; $i++) {
                    $messages[] = $lines[$i];
                }

                if (count($messages) >= $limit) {
                    break 2;
                }
            }
        }
        $caption = rex_i18n::msg('cronjob_log_caption_2');
        return self::_getList($messages, $caption);
    }

    public static function save($name, $success, $message = '', $id = null)
    {
        $year = date('Y');
        $month = date('m');

        // in den Log-Dateien festes Datumsformat verwenden
        // wird bei der Ausgabe entsprechend der lokalen Einstellungen umgewandelt
        // rex_formatter nicht verwenden, da im Frontend nicht verfuegbar
        $newline = date('Y-m-d H:i');

        if ($success) {
            $newline .= ' | SUCCESS | ';
        } else {
            $newline .= ' |  ERROR  | ';
        }

        if (!$id) {
            $id = '--';
        } else {
            $id = str_pad($id, 2, ' ', STR_PAD_LEFT);
        }

        $newline .= $id . ' | ' . $name;

        if ($message) {
            $newline .= ' | ' . str_replace(["\r\n", "\n"], ' | ', trim(strip_tags($message)));
        }

        $dir = REX_CRONJOB_LOG_FOLDER . $year;
        if (!is_dir($dir)) {
            rex_dir::create($dir);
        }

        $content = '';
        $file = $dir . '/' . $year . '-' . $month . '.log';
        if (file_exists($file)) {
            $content = rex_file::get($file);
        }

        $content = $newline . "\n" . $content;

        return rex_file::put($file, $content);
    }

    private static function _getList($lines, $caption = '')
    {
        $table_head = '';
        if (!empty($caption)) {
            $table_head .= '<caption>' . $caption . '</caption>';
        }
        $list = '
            <table class="rex-table">
                ' . $table_head . '
                <colgroup>
                    <col width="40" />
                    <col width="140" />
                    <col width="160" />
                    <col width="*" />
                </colgroup>
                <thead>
                    <tr>
                        <th class="rex-icon"></th>
                        <th>' . rex_i18n::msg('cronjob_log_date') . '</th>
                        <th>' . rex_i18n::msg('cronjob_name') . '</th>
                        <th>' . rex_i18n::msg('cronjob_log_message') . '</th>
                    </tr>
                </thead>
                <tbody>';
        if (!is_array($lines) || count($lines) == 0) {
            $list .= '
                    <tr><td colspan="4">' . rex_i18n::msg('cronjob_log_no_data') . '</td></tr>';
        } else {
            foreach ($lines as $line) {
                $data = explode(' | ', $line, 5);
                for ($i = 0; $i < 5; $i++) {
                    if (!isset($data[$i])) {
                        $data[$i] = '';
                    }
                }
                $data[0] = rex_formatter::strftime(strtotime($data[0]), 'datetime');
                $class = trim($data[1]) == 'ERROR' ? 'rex-warning' : 'rex-info';
                $data[4] = str_replace(' | ', '<br />', htmlspecialchars($data[4]));
                if ($data[2] == '--') {
                    $icon = '<span class="rex-i-element rex-i-cronjob" title="' . rex_i18n::msg('cronjob_not_editable') . '"><span class="rex-i-element-text">' . rex_i18n::msg('cronjob_not_editable') . '</span></span>';
                } else {
                    $icon = '<a href="' . rex_url::backendPage('cronjob', ['list' => 'cronjobs', 'func' => 'edit', 'oid' => trim($data[2])]) . '" title="' . rex_i18n::msg('cronjob_edit') . '"><span class="rex-i-element rex-i-cronjob"><span class="rex-i-element-text">' . rex_i18n::msg('cronjob_edit') . '</span></span></a>';
                }

                $list .= '
                    <tr class="' . $class . '">
                        <td class="rex-icon">' . $icon . '</td>
                        <td>' . $data[0] . '</td>
                        <td>' . htmlspecialchars($data[3]) . '</td>
                        <td>' . $data[4] . '</td>
                    </tr>';
            }
        }
        $list .= '
                </tbody>
            </table>';
        return $list;
    }
}
