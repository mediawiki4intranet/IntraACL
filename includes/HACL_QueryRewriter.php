<?php

/* Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * http://wiki.4intra.net/IntraACL
 * $Id$
 *
 * Based on HaloACL
 * Copyright 2009, ontoprise GmbH
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

/**
 * This file contains the class HACLQueryRewriter that modifies queries so that
 * the content of protected properties can not be read.
 *
 * @author Thomas Schweitzer
 * Date: 22.06.2009
 */
if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * This class contains two static functions that hook into the query processing of the
 * semantic stores. These functions modify queries that ask for protected semantic
 * properties. These properties are removed from the queries.
 * One function processes ASK queries, the other SPARQL (with ASK and SPARQL syntax).
 *
 * @author Thomas Schweitzer
 *
 */
class  HACLQueryRewriter  {

    //--- Constants ---

    //--- Private fields ---

    // array: The parsed sparql query that is treated
    private $mQuery;


    // array(string->bool):
    // Variables of a sparql query that are still bound after removing triples
    // have the value 'true', others are 'false'.
    private $mBoundVariables = array();

    // boolean:
    // <true> if the query was modified because of protected properties
    private $mModified = false;


    //--- Public methods ---


    /**
     * This function for the hook "RewriteQuery" modifies ASK queries. Constraints
     * and print requests for protected properties are removed.
     *
     * @param SMWQuery &$query
     *         This query is modified.
     *
     * @return bool true
     *         Returns <true> to keep the chain of hooks running.
     */
    public static function rewriteAskQuery(SMWQuery &$query) {
        $descr = $query->getDescription();
        // Remove protected properties from the query description
        $qr = new HACLQueryRewriter();

        $qr->pruneProtectedPropertiesFromAsk($descr);
        $queryString = $descr->getQueryString();
        $query->setQueryString($queryString);

        $ep = $query->getExtraPrintouts();
        $query->setExtraPrintouts($qr->prunePrintRequests($ep));

        if ($qr->mModified) {
            $query->addErrors(array(wfMsgForContent('hacl_sp_query_modified')));
        }

        return true;
    }

    /**
     * This function for the hook "RewriteSparqlQuery" modifies Sparql queries.
     * Constraints and print requests for protected properties are removed.
     *
     * @param SMWQuery &$query
     *         This query is modified.
     *
     * @return bool true
     *         Returns <true> to keep the chain of hooks running.
     */
    public static function rewriteSparqlQuery(SMWQuery &$query) {
        $qr = new HACLQueryRewriter();
        $descr = $query->getDescription();
        if (!($descr instanceof SMWSPARQLDescription)) {
            // Remove protected properties from the query description with ask
            // syntax
            $qr->pruneProtectedPropertiesFromAsk($descr);

            $queryString = $descr->getQueryString();
            $queryString = str_replace('&lt;','<',$queryString);
            $queryString = str_replace('&gt;','>',$queryString);
            $query->setQueryString($queryString);
        } else {
            // handle query with SPARQL-syntax
            $qr->pruneSparqlQuery($query);
        }

        $ep = $query->getExtraPrintouts();
        $query->setExtraPrintouts($qr->prunePrintRequests($ep));

        if ($qr->mModified) {
            $query->addErrors(array(wfMsgForContent('hacl_sp_query_modified')));
        }

        return true;
    }


    //--- Private methods ---

    /**
     * This function recursively traverses the query's hierarchy and removes
     * all protected property from descriptions and their print requests.
     *
     * @param SMWDescription $descr
     *         The description which is pruned.
     */
    private function pruneProtectedPropertiesFromAsk(SMWDescription $descr) {
        // Remove protected properties from the print requests
        $printRequests = $this->prunePrintRequests($descr->getPrintRequests());
        $descr->setPrintRequests($printRequests);

        if (!($descr instanceof SMWConjunction ||
              $descr instanceof SMWDisjunction)) {
               return;
        }

        $descriptions = $descr->getDescriptions();
        $remDescr = array();
        for ($i = 0, $num = count($descriptions); $i < $num; ++$i) {
            $d = $descriptions[$i];
            if ($d instanceof SMWSomeProperty) {
                // Check if property is protected
                $prop = $d->getProperty();
                $wpv = $prop->getWikiPageValue();
                if ($wpv) {
                    $id = $wpv->getTitle()->getArticleID();
                    global $wgUser;
                    $allowed = HACLEvaluator::hasPropertyRight($id,
                                                       $wgUser->getId(), HACLLanguage::RIGHT_READ);
                    if ($allowed) {
                        // Access to property is allowed => check for further
                        // subqueries of the property
                        $pd = $d->getDescription();
                        if ($pd) {
                            $this->pruneProtectedPropertiesFromAsk($pd);
                        }
                    } else {
                        $this->mModified = true;
                        $remDescr[] = $i;
                    }
                }
            } else {
                $this->pruneProtectedPropertiesFromAsk($d);
            }
        }
        $descr->removeDescriptions($remDescr);

    }

    /**
     * This function removes print requests for protected properties from
     * the given array of print requests.
     *
     * @param array(SMWPrintRequest) $printRequests
     *         This array is pruned.
     *
     * @return array(SMWPrintRequest)
     *         The pruned array.
     */
    private function prunePrintRequests($printRequests) {
        foreach ($printRequests as $key => $pr) {
            if ($pr->getMode() == SMWPrintRequest::PRINT_PROP) {
                $prop = $pr->getData();
                $wpv = $prop->getWikiPageValue();
                if ($wpv) {
                    $id = $wpv->getTitle()->getArticleID();
                    global $wgUser;
                    $allowed = HACLEvaluator::hasPropertyRight($id,
                                                       $wgUser->getId(), HACLLanguage::RIGHT_READ);
                    if (!$allowed) {
                        // Invalid print request => remove it
                        unset($printRequests[$key]);
                        $this->mModified = true;
                    }
                }
            }
        }
        return $printRequests;
    }

    /**
     * This function parses a Sparql query and removes all triples with protected
     * properties or variables at property position.
     *
     * @param SMWQuery $query
     *         The query object that contains the Sparql query
     */
    private function pruneSparqlQuery(SMWQuery &$query) {

        $store = smwfGetStore();
        if (!$store instanceof SMWTripleStore) {
            return;
        }

        $prefixes = str_replace(':<', ': <', TSNamespaces::getAllPrefixes());
        $queryString = $prefixes . $query->getQueryString();

        /* ARC2 static class inclusion */
        global $haclgIP;
        include_once("$haclgIP/arc/ARC2.php");

        /* parser instantiation */
        $parser = ARC2::getSPARQLParser();

        /* parse a query */
        $parser->parse($queryString);
        if (!$parser->getErrors()) {
            $q = $parser->getQueryInfos();

            $this->mQuery = &$q;
            $this->pruneProtectedPropertiesFromSparql($q);

            $qs = $this->serializeSparqlQuery($q);
            if ($qs == 'SELECT WHERE' ||
                strpos($qs, "WHERE") == strlen($qs) - 5) {
                // No triples remained after pruning
                $query->addErrors(array(wfMsgForContent('hacl_sp_empty_query')));
                $query->setQueryString("");
                return;
            }

            // replace IRIs in prefixes
            $prefixes = $q['prefixes'];
            foreach ($prefixes as $pre => $iri) {
                $qs = str_replace($iri, "$pre", $qs);
            }

            $query->setQueryString($qs);

//            print_r($q);
        }
        else {
            // Parsing the query failed => add error messages
            $query->addErrors($parser->getErrors());
        }
    }

    /**
     * Prunes the parsed Sparql query. Protected properties and variables at
     * property position are marked as 'protected'. Variables that lose their
     * binding are removed from the result variables.
     *
     * @param array(string => array) $query
     *         The parsed query is given by reference as the parsed tree is modified
     *         "in place".
     */
    private function pruneProtectedPropertiesFromSparql(&$query) {
        if (!array_key_exists('query', $query)) {
            // No query found
            return;
        }
        $q = &$query['query'];
        if ($q['type'] != 'select') {
            // Query is not of type select
            return;
        }

        // Mark patterns with protected properties as 'protected'
        $this->pruneSparqlPattern($q['pattern']);

        // Mark filters with unbound variables as 'protected'
        $this->pruneUnboundFilters($q['pattern']);

        // Remove unbound variables from result variables
        $resultVars = &$q['result_vars'];
        foreach ($this->mBoundVariables as $variable => $bound) {
            if (!$bound) {
                foreach ($resultVars as $idx => &$rv) {
                    if ($rv['value'] == $variable) {
                        unset($resultVars[$idx]);
                        break;
                    }
                }
            }
        }

    }

    /**
     * This function recurses through the parsed Sparql query and marks triples
     * with protected properties as 'protected'.
     *
     * @param array(string => array) $pattern
     *         A pattern in the parsed query.
     */
    private function pruneSparqlPattern(&$pattern) {
        switch ($pattern['type']) {
            case 'group':
            case 'union':
            case 'optional':
                $patterns = &$pattern['patterns'];
                $protected = true;
                foreach($patterns as &$p) {
                    $this->pruneSparqlPattern($p);
                    if (!array_key_exists('protected', $p)) {
                        $protected = false;
                    }
                }
                if ($protected) {
                    // all children are protected => this node is protected as well
                    $pattern['protected'] = true;
                }
                break;
            case 'triples':
                // Prune all triples in this node.
                $allTriplesProtected = $this->pruneTriples($pattern['patterns']);
                if ($allTriplesProtected) {
                    $pattern['protected'] = true;
                }
                break;
            default:
                break;
        }

    }

    /**
     * Recurses through the parsed Sparql query and marks filters with unbound
     * variables as 'protected'.
     *
     * @param array(string => array) $pattern
     *         A pattern in the parsed query.
     */
    private function pruneUnboundFilters(&$pattern) {
        switch ($pattern['type']) {
            case 'group':
            case 'union':
            case 'optional':
                $patterns = &$pattern['patterns'];
                foreach($patterns as &$p) {
                    $this->pruneUnboundFilters($p);
                }
                break;
            case 'filter':
                $this->pruneUnboundFilter($pattern);
        }

    }

    /**
     * Marks a single filter pattern as 'protected' if it contains unbound
     * variables.
     *
     * @param array(string => array) $pattern
     *         A filter pattern in the parsed query.
      */
    private function pruneUnboundFilter(&$patterns) {
        $constraint = $patterns['constraint'];
        $expr = $constraint['patterns'];
        $op1 = $expr[0];
        $op2 = $expr[1];

        if ($op1['type'] == 'var') {
            $op1 = $op1['value'];
            if (array_key_exists($op1, $this->mBoundVariables)
                && $this->mBoundVariables[$op1] === false) {
                $patterns['protected'] = true;
            }
        }

        if ($op2['type'] == 'var') {
            $op2 = $op2['value'];
            if (array_key_exists($op2, $this->mBoundVariables)
                && $this->mBoundVariables[$op2] === false) {
                $patterns['protected'] = true;
            }
        }

    }

    /**
     * Marks all triples in a triple pattern which contain a protected property
     * as 'protected'.
     *
     * @param array(string => array) $triples
     *         A triple pattern with at least one triple.
     * @return boolean
     *         <true>, if all triples in the pattern are protected.
     *         <false>, if at least one triple is not protected.
     */
    private function pruneTriples(&$triples) {
        $propNs = $this->mQuery['prefixes'];
        $propNs = $propNs['prop:'];
        $allProtected = true;
        foreach ($triples as &$t) {
            $allowed = true;

            if ($t['p_type'] == 'var') {
                // Variables are not allowed for predicates
                $allowed = false;
            } else if ($t['p_type'] == 'uri') {
                $pred = $t['p'];
                if (strpos($pred, $propNs) === 0) {
                    // Property starts with namespace for SMW properties
                    // => check is access is allowed
                    $propName = substr($pred, strlen($propNs));
                    $etc = haclfDisableTitlePatch();
                    $prop = Title::newFromText($propName, SMW_NS_PROPERTY);
                    haclfRestoreTitlePatch($etc);
                    $id = $prop->getArticleID();
                    global $wgUser;
                    $allowed = HACLEvaluator::hasPropertyRight($id,
                                                       $wgUser->getId(), HACLLanguage::RIGHT_READ);
                }
            }

            if (!$allowed) {
                // The triple contains a protected property.
                // => Triple will be ignored
                $t['protected'] = true;
                $this->mModified = true;
            } else {
                $allProtected = false;
            }
            // Variables may become unbound
            if ($t['s_type'] == 'var') {
                // The subject is a variable that may no longer be bound by this triple
                if (!array_key_exists($t['s'], $this->mBoundVariables) && !$allowed) {
                    $this->mBoundVariables[$t['s']] = false;
                } else if ($allowed) {
                    $this->mBoundVariables[$t['s']] = true;
                }
            }

            if ($t['o_type'] == 'var') {
                // The object is a variable that may no longer be bound by this triple
                if (!array_key_exists($t['o'], $this->mBoundVariables) && !$allowed) {
                    $this->mBoundVariables[$t['o']] = false;
                } else if ($allowed) {
                    $this->mBoundVariables[$t['o']] = true;
                }
            }

        }
        return $allProtected;
    }

    /**
     * Creates a Sparql query string from the structure of a parsed query. Patterns
     * that are marked as protected are ignored.
     *
     * @param array $query
     *         The parsed and pruned query structure.
     * @return string
     *         The assembled Sparql query string.
     */
    private function serializeSparqlQuery($query) {
        if (!array_key_exists('query', $query)) {
            // No query found
            return '';
        }
        $q = $query['query'];
        if ($q['type'] != 'select') {
            // Query is not of type select
            return '';
        }

        $qs = "SELECT ";

        $vars = $q['result_vars'];
        foreach ($vars as $v) {
            $qs .= '?'.$v['value'].' ';
        }

        $qs .= "WHERE";

        $patterns = $q['pattern'];

        $qs .= $this->serializePattern($patterns);

        return $qs;
    }

    /**
     * Creates a part of a sparql query string from a parsed sparql pattern.
     * Protected patterns are ignored.
     *
     * @param array $pattern
     *         A pattern in the structure of a sparql query
     * @return string
     *         The string representation of the pattern.
     */
    private function serializePattern($pattern) {
        if (@$pattern['protected'] === true) {
            // This pattern contains protected predicates. They are not serialized.
            return '';
        }
        switch ($pattern['type']) {
            case 'group':
                return $this->serializeGroup($pattern['patterns']);
            case 'triples':
                return $this->serializeTriples($pattern['patterns']);
            case 'union':
                return $this->serializeUnion($pattern['patterns']);
            case 'optional':
                return $this->serializeOptional($pattern['patterns']);
            case 'filter':
                return $this->serializeFilter($pattern);
        }

    }

    /**
     * Generates the string representation of a group pattern.
     *
     * @param array $patterns
     *         A group pattern
     * @return string
     *         String representation of the group pattern.
     */
    private function serializeGroup($patterns) {
        $qs = "\n{";

        foreach($patterns as $p) {
            $qs .= $this->serializePattern($p);
        }
        $qs .= "}\n";
        return $qs;
    }

    /**
     * Generates the string representation of a union pattern.
     *
     * @param array $patterns
     *         A union pattern
     * @return string
     *         String representation of the union pattern.
     */
    private function serializeUnion($patterns) {
        $qs = '';
        $first = true;
        foreach($patterns as $p) {
            if (!$first) {
                $qs .= ' UNION ';
            }
            $qs .= $this->serializePattern($p);
            $first = false;
        }
        return $qs;
    }

    /**
     * Generates the string representation of a 'optional' pattern.
     *
     * @param array $patterns
     *         A 'optional' pattern
     * @return string
     *         String representation of the 'optional' pattern.
     */
    private function serializeOptional($patterns) {
        $qs = "\nOPTIONAL {";
        foreach($patterns as $p) {
            $qs .= $this->serializePattern($p);
        }
        return $qs ."}";
    }

    /**
     * Generates the string representation of a filter pattern.
     *
     * @param array $patterns
     *         A filter pattern
     * @return string
     *         String representation of the filter pattern.
     */
    private function serializeFilter($patterns) {
        $constraint = $patterns['constraint'];
        $expr = $constraint['patterns'];
        $op1 = $expr[0];
        $op2 = $expr[1];
        $operator = $constraint['operator'];

        if ($op1['type'] == 'var') {
            $op1 = '?' . $op1['value'];
        } else if ($ob1['type'] == 'literal') {
            $op1 = '"'.$op1['value'].'"^^'.$op1['datatype'];
        }

        if ($op2['type'] == 'var') {
            $op2 = '?' . $op2['value'];
        } else if ($op2['type'] == 'literal') {
            $op2 = '"'.$op2['value'].'"^^'.$op2['datatype'];
        }

        $qs = "FILTER ($op1 $operator $op2)";
        return $qs;
    }

    /**
     * Generates the string representation of a triple pattern.
     *
     * @param array $patterns
     *         A triple pattern
     * @return string
     *         String representation of the triple pattern.
     */
    private function serializeTriples($triples) {
        $qs = '';
        foreach ($triples as $t) {
            if (@$t['protected'] === true) {
                // Found a protected triple => do not serialize
                continue;
            }
            $subj = $t['s'];
            if ($t['s_type'] == 'var') {
                $subj = '?' . $subj;
            }

            $pred = $t['p'];
            $obj = $t['o'];
            if ($t['o_type'] == 'var') {
                $obj = '?' . $obj;
            } else if ($t['o_type'] == 'literal') {
                $obj = addslashes($obj);
                $obj = '"'.$obj.'"^^'.$t['o_datatype'];
            }

            $qs .= "\n$subj $pred $obj .\n";
        }
        return $qs;
    }

}