<?php

/**
 * Copyright (c) 2010+,
 *   Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *   Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 *
 * This file is part of IntraACL MediaWiki extension
 * http://wiki.4intra.net/IntraACL
 *
 * The IntraACL-Extension is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The IntraACL-Extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class IntraACL_SQL_QuickACL
{
    /**
     * Save Quick ACL options for a user
     */
    public function saveQuickAcl($user_id, $sd_ids, $default_sd_id = NULL)
    {
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('intraacl_quickacl', array('user_id' => $user_id), __METHOD__);
        $rows = array();
        foreach ($sd_ids as $sd_id)
        {
            $rows[] = array(
                'pe_type'    => $sd_id[0],
                'pe_id'      => $sd_id[1],
                'user_id'    => $user_id,
                'qa_default' => $default_sd_id == $sd_id ? 1 : 0,
            );
        }
        $dbw->insert('intraacl_quickacl', $rows, __METHOD__);
    }

    /**
     * Get Quick ACL options for a user
     */
    public function getQuickacl($user_id)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('intraacl_quickacl', '*', array('user_id' => $user_id), __METHOD__);
        $sd_ids = array();
        $default_id = NULL;
        foreach ($res as $row)
        {
            $sd_ids[] = array($row->pe_type, $row->pe_id);
            if ($row->qa_default)
            {
                $default_id = array($row->pe_type, $row->pe_id);
            }
        }
        return array($sd_ids, $default_id);
    }

    /**
     * Delete Quick ACL entries for SD
     */
    public function deleteQuickaclForSD($peType, $peID)
    {
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('intraacl_quickacl', array('pe_type' => $peType, 'pe_id' => $peID), __METHOD__);
        return true;
    }
}
