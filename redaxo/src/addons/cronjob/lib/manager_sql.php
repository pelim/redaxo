<?php

/**
 * Cronjob Addon
 *
 * @author gharlan[at]web[dot]de Gregor Harlan
 *
 * @package redaxo\cronjob
 */

class rex_cronjob_manager_sql
{
    private $sql;
    private $manager;

    private function __construct(rex_cronjob_manager $manager = null)
    {
        $this->sql = rex_sql::factory();
        // $this->sql->setDebug();
        $this->manager = $manager;
    }

    public static function factory(rex_cronjob_manager $manager = null)
    {
        return new self($manager);
    }

    public function getManager()
    {
        if (!is_object($this->manager)) {
            $this->manager = rex_cronjob_manager::factory();
        }
        return $this->manager;
    }

    public function hasManager()
    {
        return is_object($this->manager);
    }

    public function setMessage($message)
    {
        $this->getManager()->setMessage($message);
    }

    public function getMessage()
    {
        return $this->getManager()->getMessage();
    }

    public function hasMessage()
    {
        return $this->getManager()->hasMessage();
    }

    public function getName($id)
    {
        $this->sql->setQuery('
            SELECT  name
            FROM    ' . REX_CRONJOB_TABLE . '
            WHERE   id = ?
            LIMIT   1
        ', [$id]);
        if ($this->sql->getRows() == 1) {
            return $this->sql->getValue('name');
        }
        return null;
    }

    public function setStatus($id, $status)
    {
        $this->sql->setTable(REX_CRONJOB_TABLE);
        $this->sql->setWhere(['id' => $id]);
        $this->sql->setValue('status', $status);
        $this->sql->addGlobalUpdateFields();
        try {
            $this->sql->update();
            $success = true;
        } catch (rex_sql_exception $e) {
            $success = false;
        }
        $this->saveNextTime();
        return $success;
    }

    public function setExecutionStart($id, $reset = false)
    {
        $this->sql->setTable(REX_CRONJOB_TABLE);
        $this->sql->setWhere(['id' => $id]);
        $this->sql->setDateTimeValue('execution_start', $reset ? 0 : time());
        try {
            $this->sql->update();
            return true;
        } catch (rex_sql_exception $e) {
            return false;
        }
    }

    public function delete($id)
    {
        $this->sql->setTable(REX_CRONJOB_TABLE);
        $this->sql->setWhere(['id' => $id]);
        try {
            $this->sql->delete();
            $success = true;
        } catch (rex_sql_exception $e) {
            $success = false;
        }
        $this->saveNextTime();
        return $success;
    }

    public function check()
    {
        $sql = rex_sql::factory();
        // $sql->setDebug();
        $sql->setQuery('
            SELECT    id, name, type, parameters, `interval`, execution_moment
            FROM      ' . REX_CRONJOB_TABLE . '
            WHERE     status = 1
                AND   execution_start < ?
                AND   environment LIKE ?
                AND   nexttime <= ?
            ORDER BY  nexttime ASC, execution_moment DESC, name ASC
            LIMIT     1
        ', [rex_sql::datetime(time() - 2 * ini_get('max_execution_time')), '%|' . (int) rex::isBackend() . '|%', rex_sql::datetime()]);
        if ($sql->getRows() != 0) {
            ignore_user_abort(true);
            register_shutdown_function([$this, 'timeout'], $sql);
            $this->setExecutionStart($sql->getValue('id'));
            if ($sql->getValue('execution_moment') == 1) {
                $this->tryExecuteSql($sql, true, true);
            } else {
                rex_extension::register(
                    'RESPONSE_SHUTDOWN',
                    function () use ($sql) {
                        $this->tryExecuteSql($sql, true, true);
                    }
                );
            }
        } else {
            $this->saveNextTime();
        }
    }

    public function timeout(rex_sql $sql)
    {
        if (connection_status() != 0) {
            if ($this->hasManager() && $this->getManager()->timeout()) {
                $this->setNextTime($sql->getValue('id'), $sql->getValue('interval'), true);
            } else {
                $this->setExecutionStart($sql->getValue('id'), true);
                $this->saveNextTime();
            }
        }
    }

    public function tryExecute($id, $log = true)
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT    id, name, type, parameters, `interval`
            FROM      ' . REX_CRONJOB_TABLE . '
            WHERE     id = ? AND environment LIKE ?
            LIMIT     1
        ', [$id, '%|' . (int) rex::isBackend() . '|%']);
        if ($sql->getRows() != 1) {
            $this->getManager()->setMessage('Cronjob not found in database');
            $this->saveNextTime();
            return false;
        }
        return $this->tryExecuteSql($sql, $log);
    }

    public function tryExecuteSql(rex_sql $sql, $log = true, $resetExecutionStart = false)
    {
        if ($sql->getRows() > 0) {
            $id       = $sql->getValue('id');
            $name     = $sql->getValue('name');
            $type     = $sql->getValue('type');
            $params   = json_decode($sql->getValue('parameters'), true);
            $interval = $sql->getValue('interval');

            $cronjob = rex_cronjob::factory($type);
            $success = $this->getManager()->tryExecute($cronjob, $name, $params, $log, $id);

            $this->setNextTime($id, $interval, $resetExecutionStart);

            return $success;
        }
        return false;
    }

    public function setNextTime($id, $interval, $resetExecutionStart = false)
    {
        $nexttime = self::calculateNextTime($interval);
        $add = $resetExecutionStart ? ', execution_start = 0' : '';
        try {
            $this->sql->setQuery('
                UPDATE  ' . REX_CRONJOB_TABLE . '
                SET     nexttime = ?' . $add . '
                WHERE   id = ?
            ', [rex_sql::datetime($nexttime), $id]);
            $success = true;
        } catch (rex_sql_exception $e) {
            $success = false;
        }
        $this->saveNextTime();
        return $success;
    }

    public function getMinNextTime()
    {
        $this->sql->setQuery('
            SELECT  UNIX_TIMESTAMP(MIN(nexttime)) AS nexttime
            FROM    ' . REX_CRONJOB_TABLE . '
            WHERE   status = 1
        ');
        if ($this->sql->getRows() == 1) {
            return $this->sql->getValue('nexttime');
        }
        return null;
    }

    public function saveNextTime($nexttime = null)
    {
        if ($nexttime === null) {
            $nexttime = $this->getMinNextTime();
        }
        if ($nexttime === null) {
            $nexttime = 0;
        } else {
            $nexttime = max(1, $nexttime);
        }

        rex_config::set('cronjob', 'nexttime', $nexttime);
        return true;
    }

    public static function calculateNextTime($interval)
    {
        $interval = explode('|', trim($interval, '|'));
        if (is_array($interval) && isset($interval[0]) && isset($interval[1])) {
            $date = getdate();
            switch ($interval[1]) {
                case 'i': return mktime($date['hours'], $date['minutes'] + $interval[0], 0);
                case 'h': return mktime($date['hours'] + $interval[0], 0, 0);
                case 'd': return mktime(0, 0, 0, $date['mon'], $date['mday'] + $interval[0]);
                case 'w': return mktime(0, 0, 0, $date['mon'], $date['mday'] + $interval[0] * 7 - $date['wday']);
                case 'm': return mktime(0, 0, 0, $date['mon'] + $interval[0], 1);
                case 'y': return mktime(0, 0, 0, 1, 1, $date['year'] + $interval[0]);
            }
        }
        return null;
    }
}
