<?php
/**
 * outings actions.
 *
 * @package    metaengine
 * @subpackage outings
 * @author     Oliver Christen, Camptocamp SA
 * @version    SVN: $Id: actions.class.php 2503 2007-12-11 16:00:14Z alex $
 */
class outingsActions extends sfActions
{

    private $errorLog;
    private $currentOutingId;
    private $errorStack = array();

    /**
     * Temporary function
     * we only need the template to test the sending of xml via POST
     */
    //public function executeTestXml() {}
    //public function executeTestQuery() {}

    /**
     * PULL
     */

    /**
     * recover query string, return rss
     */
    public function executeQuery() {
        // reinitialize error log
        $this->errorLog = array();
        // parse query parameters and initiate the db query
        $this->parseQuery();
        // return feedback
        $this->feedback();
    }  

    private function parseQuery() {
        
        // get query parameters (using dynamic variable $$)
        $plist = array('outing_name', 'region_name', 'region_id', 'system_id', 
                       'outing_date', 'outing_lang', 'activity_ids');
        $tlist = array('o.name', 'r.name', 'r.id', 'o.source_id', 'o.date', 'o.lang', 'o.activity_ids');
        $olist = array('ILIKE', 'ILIKE', '=', '=', '', '=', '=');
        foreach ($plist as $param) {
            $$param = isset($_REQUEST[$param]) ? $_REQUEST[$param] : '';
        }
        $order_by = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $limit = isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? 
                 $_REQUEST['limit'] : sfConfig::get('app_maxresults');

        // explode values to array
        // appart for outing_date, duplicate value wil cause an error in doctrine
        foreach ($plist as $param) {
            $$param = !empty($$param) ? explode(',',$$param) : NULL;

            if ($param != 'outing_date') {
                 $$param = array_unique($$param);
            }
        }

        // generate where clauses, loop on $plist and $tlist
        $condition = '';
        $conditionvalues = array();

        // be sure not to retrieve outings from the future
        $condition .= '(o.date <= ?)';
        $conditionvalues[] = date("Y-m-d");

        foreach ($plist as $param) {
            // treate other params
            $s = sizeof($$param);
            if($s > 0) {

                $condition .= ' AND (';

                $p = 1;

                // get key position for given value
                $k = array_search($param, $plist);
                
                if ($param == 'outing_date') {
                    // check if date is a valid date
                    $ok = true;
                    foreach ($$param as $date) {
                        if(preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", 
                                      $date, $regs)) {
                            if (!checkdate($regs[2], $regs[3], $regs[1])) {
                                
                                $ok = false;
                            }
                        } else {
                            $ok = false;
                        }
                    }
                    if ($ok) {
                        /* dynamic var seems to cause some problem later, so using a 
                        temp variable */
                        $dates = $$param;
                        sort($dates);
                        for ($i = 0; $i < $s; $i++) {
                            if ($i > 0) {
                                $condition .= ' AND ';
                            }
                            $date = $dates[$i];
                            if(preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})(.*)$/", 
                                          $date, $regs)) {
                                $o = $i == 0 ? '>=' : '<=';
                                $condition .= $tlist[$k].' '.$o.' ?';
                                $conditionvalues[] = $date;
                            } else {
                                //return $this->queryError(0);
                                $this->logError('queryError', 0);
                                return;
                            }
                        }
                    }
                } else if ($param == 'region_name') {
                    foreach ($$param as $pvalue) {
                        $condition .= $tlist[$k].' '.$olist[$k].' ? OR ';
                        $condition .= 'o.region_name '.$olist[$k].' ?';
                        $rname = $$param;
                        // twice the same value to search, once in r.name, once 
                        // in o.region_name
                        $conditionvalues[] = '%'.$pvalue.'%';
                        $conditionvalues[] = '%'.$pvalue.'%';
                        if ($s > 1 && ${$param}[$s-1] != $pvalue) {
                            $condition .= ' OR ';
                        }
                    }
                } else {
                    foreach($$param as $pvalue) {
                        // recover the correct table target for given key
                        // recover the operator for given key
                        
                        if ($param == 'activity_ids') {
                            $condition .= ' ? '.$olist[$k].' ANY ('.$tlist[$k].') ';
                        } else {
                            $condition .= $tlist[$k].' '.$olist[$k].' ?';
                        }
                        
                        $v = $olist[$k] == '=' ? $pvalue : '%'.$pvalue.'%';
                        $conditionvalues[] = $v;
                        /* special syntax below to access array value from 
                        dynamicly named array */
                        if ($s > 1 && ${$param}[$s-1] != $pvalue) {
                            $condition .= ' OR ';
                        }
                    }
                }

                $condition .= ')';
            }
            
        }
        
        $sql = 'SELECT o.name as outing_name, o.date, o.source_id, r.name as region_saved_name, ' .
               'o.region_name, o.elevation, o.activity_ids, o.rating, o.facing, o.lang, o.url ' .
               'FROM outings o ' .
               'LEFT JOIN outings_regions ore ON o.id = ore.outing_id ' .
               'LEFT JOIN regions r ON ore.region_id = r.id AND r.system_id = \'1\' ';
               // TODO: use a parameter to return regions from an other regions system
        
        if (sizeof($conditionvalues) > 0)
        {
            $sql .= "WHERE $condition ";
        }

        // treat orderby
        if (!empty($order_by)) {
            if (!in_array($order_by, $plist)) {
                //return $this->queryError(1);
                $this->logError('queryError', 1);
                return;
            }
            $k = array_search($order_by, $plist);
            $order_by_tbl = $tlist[$k];
            $sql .= "ORDER BY $order_by_tbl DESC ";
        } else {
            // default order by date
            $sql .= 'ORDER BY o.date DESC ';
        }
        // limit results maxresults
        $sql .= "LIMIT $limit";

        // execute query
        $r = sfDoctrine::connection()->standaloneQuery($sql, $conditionvalues)->fetchAll();

        // generate rss with result
        $rss = 
'<?xml version="1.0" encoding="utf-8" ?>
<rss version="2.0">
   <channel>
      <title>Syndication de sorties multi-sites</title>
      <language>fr</language>
      <pubDate />
   </channel>
</rss>';

        $xml = new SimpleXMLElement($rss);
        // set current date
        $xml->channel->pubDate = date(DATE_RFC822);

        // add item for all result from query
        $clients = sfConfig::get('app_auth_clientnames');
        $activities = sfConfig::get('app_activities_values');
        $facing = sfConfig::get('app_facing_values');

        foreach ($r as $result) {
            $item = $xml->channel->addChild('item');
            
            // title
            if(preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})(.*)$/", 
                          $result['date'], $regs)) {
                
                setlocale(LC_TIME, 'fr');
                $formatedDate = strftime('%Y-%m-%d', mktime(0, 0, 0, $regs[2], 
                                        $regs[3], $regs[1]));
                $encodedDate = utf8_encode(ucfirst($formatedDate));
            }
            $item->addChild('title', htmlspecialchars($result['outing_name']));
            // date 
            $item->addChild('pubDate', htmlspecialchars($encodedDate));
            // author 
            $item->addChild('author', htmlspecialchars($clients[$result['source_id']] . ' (' . $clients[$result['source_id']] . ')'));

            // description
            $descrValue = '';
            if (!empty($result['region_saved_name']))
            {
                $descrValue .= htmlspecialchars($result['region_saved_name']);  
            }
            elseif (!empty($result['region_name']))
            {
                $descrValue .= htmlspecialchars($result['region_name']);  
            }
            
            $descrValue .= ' , '.$result['elevation'].'m , ';
            
            if (!empty($result['activity_ids'])) {
                $acts = $this->stringToArray($result['activity_ids']);
                $nbact = sizeof($acts);
                if ($nbact > 0) {
                    foreach ($acts as $act) {
                        $descrValue .= $activities[$act];
                        if ($act != $acts[$nbact-1]) {
                            $descrValue .= ' / ';
                        }
                    }
                }
            }
            
            $descrValue .= ' , ';
            $descrValue .= $result['rating'] . ' , ';
            
            if (array_key_exists($result['facing'], $facing)) {
                $descrValue .= $facing[$result['facing']];
            }
            $descrValue .= ' , ';

            $descrValue .= $result['lang'];
            $item->addChild('description', $descrValue);

            // link
            $linkValue = $result['url'];
            $item->addChild('link', htmlspecialchars($linkValue));            
        }
        
        $this->rss = $xml->asXML();

    }

    /**
     * PUSH
     */

    /**
     * check/parse/treate received xml via POST
     */
    public function executePush() {

        // reinitialize error log
        $this->errorLog = array();
        
        // authenticate client
        $this->checkClientAuthentication();

        if ($this->clientAllowed) {
            // start parsing string
            $this->parseXml();
        }

        $this->feedback();
    }

    /**
     * check if client id and key are correct
     */
    private function checkClientAuthentication() {
        $this->authError = false;
        $this->clientAllowed = false;
        
        $this->authid = $this->getRequestParameter('metaengine_user_id');
        $this->authkey = $this->getRequestParameter('metaengine_user_key');

        if (empty($this->authid) || empty($this->authkey)) {
            //return $this->authError(0);
            $this->logError('authError', 0);
            return;
        }

        // check if key matches
        $keys = sfConfig::get('app_auth_keys');
        if (!array_key_exists($this->authid, $keys) || $this->authkey != $keys[$this->authid]) {
           //return $this->authError(0);
           $this->logError('authError', 0);
           return;
        }

        // client ok
        $this->clientAllowed = true;
    }

    /**
     * check/parse/treate received xml via POST
     */
    private function parseXml() {

        $this->xmlParseError = false;

        $this->xml = $this->getRequestParameter('metaengine_xml');

        $xmlstr = trim($this->xml);

        // check if the string received is xml
        /* check on xml, to enable if we want to get a better return error to 
           the user

        // check 0 is xml ?
        $tag1 = substr($xmlstr, 0, 1);
        if ($tag1 != '<') {
            //return $this->parseXmlError(0);
        }
        // check 1 has header <?xml version="1.0" foobar ? >
        $tag2 = substr($xmlstr, 1, 4);
        if ($tag2 == '?xml') {
            // check 2 has terminating tag ?
            $tag3 = strpos($xmlstr, '?>');
            if ($tag3 === false) {
                //return $this->parseXmlError(2);
            }
            
            // check 3 does "version" exist
            $xmldeclaration = substr($xmlstr, 0, $tag3+2);
            $xmldeclaration = str_replace(' ', '', $xmldeclaration);
            
            $versionpos = strpos($xmldeclaration, 'version');
            if ($versionpos === false) {
                return $this->parseXmlError(3);
            }
            
            // 4 check if the version tag is well formed
            if (!preg_match('/^<\?xml ?version ?= ?["\']1\.0["\'](.*)\?>$/', 
                $xmldeclaration, $regs)) {
                //return $this->parseXmlError(4);
            }

            // 5 check if next char is <
            $end = $tag3+2 > strlen($xmlstr) ? strlen($xmlstr) : $tag3+2;
            $tag4 = substr($xmlstr, $end, 1);
            while (ord($tag4) == 10 || ord($tag4) == 13) {
                $end++;
                if ($end > strlen($xmlstr)) {
                    //return $this->parseXmlError(5);
                } else {
                    $tag4 = substr($xmlstr, $end, 1);    
                }
            }
            if ($tag4 != '<') {
                //return $this->parseXmlError(5);
            }
        }
        // 6 check if at least a well formed tag exist
        if (isset($tag4)) {
            $body = substr($xmlstr, $end, strlen($xmlstr));
        } else {
            $body = $xmlstr;
        }
        $e1 = strpos($body, '>');
        $e2 = strrpos($body, '>');
        $s2 = strrpos($body, '<');
        if ($e1 == $e2) {
            if (substr($body, $e1-1, 1) != '/') {
                //return $this->parseXmlError(6);
            }
        } else {
            $t1 = substr($body, 1, $e1-1);
            $t2 = substr($body, $s2+1, $e2-$s2-1);
            if (substr($t2, 0, 1) != '/') {
                //return $this->parseXmlError(8);
            }
            if ($t1 != substr($t2, 1, $e2-$s2)) {
                //return $this->parseXmlError(7);
            }
        }
        */

        // try loading xml
        $xml = new DOMDocument();
        if (!@$xml->loadXML(urldecode($xmlstr))) {
            //return $this->parseXmlError(9);
            $this->logError('xmlError', 9);
            return;
        }
        // @ to prevent warning in case xml is not valid
        
        /*
        // check if xml is valid against schema
        $schemafile = SF_ROOT_DIR.DIRECTORY_SEPARATOR.'web'.
                      DIRECTORY_SEPARATOR.'metaengineschema.xsd';
        if(!is_file($schemafile)) {
            //return $this->parseXmlError(10);
            return $this->logError('xmlError', 10);
        }
        if (!@$xml->schemaValidate($schemafile)) {
            return $this->parseXmlError(11);
        }*/

        // xml ok
        // start processing data
        $sxml = simplexml_import_dom($xml);

        $this->idcount = 0;

        // loop on all outing
        foreach ($sxml->outing as $outingxml) {

            $this->idcount++;
            
            // in case of error, set to true to skip current code execution and continue with next foreach iteration
            $skip = false;

            $status = $outingxml->status;
            /* set to 0 by default
                0 = add
                1 = edit
                2 = delete */
            $status = !isset($status) || empty($status)  ? 0 : $status;

            // store outing id
            $this->currentOutingId = $outingxml->original_outing_id;

            // get DB object and table structure
            switch ($status) {
                case 0 : // add
                    // check that the original_outing_id doesnt already exist
                    $checkdb = Doctrine_Query::create()->
                               select('o.id')->
                               from('Outing o')->
                               where('o.original_outing_id = ? AND o.source_id = ?', 
                               array($outingxml->original_outing_id, $this->authid))->
                               execute();
                    if (sizeof($checkdb) > 0) {
                        //return $this->parseXml(19);
                        $this->logError('xmlError', 19);
                        $skip = true;
                    } else {
                        // create new outing
                        $outingdb = new Outing();
                    }
                break;
                case 1: // edit
                case 2: // delete

                    // check if id is set
                    if (!isset($outingxml->original_outing_id) || empty($outingxml->original_outing_id) || 
                        $outingxml->original_outing_id == '') {
                        //return $this->dbError(1);
                        $this->logError('dbError', 1);
                        $skip = true;
                    } else {
                        // query db
                        $outingdb = Doctrine_Query::create()->
                                from('Outing o')->
                                where('o.original_outing_id = ? AND o.source_id = ?', 
                                array($outingxml->original_outing_id, $this->authid))->
                                execute();
                        // FIXME: add primary keys to outing table (original_outing_id AND source_id) so that this query is boosted.
                        if (sizeof($outingdb) < 1) {
                            //return $this->dbError(2);
                            $this->logError('dbError', 2);
                            $skip = true;
                        } else {
                            // get rid of the array
                            $outingdb = $outingdb[0];
                        }
                    }
                break;
            }
            
            if ($skip) {
                continue;
            } else {
                // if add or update, retrieve data and save
                if ($status < 2) {

                    // if add or update, continue with data recording
                    $outingTbl = $outingdb->getTable('Outings');
                    $outingColumns = $outingTbl->getColumnNames();

                    // loop on all parameters
                    foreach ($outingColumns as $column) {
                        if (!$skip) {
                            if ($column == 'id') continue; // skip id

                            $colDef = $outingTbl->getDefinitionOf($column);

                            if ($column == 'activity_ids') {
                                // outing <-> activity relation
                                $activitiesList = $outingxml->activity;
                                if (sizeof($activitiesList) < 1) {
                                    //return $this->parseXmlError(14);
                                    $this->logError('xmlError', 14, $column);
                                    $skip = true;
                                } else {
                                    $acts = array();
                                    foreach ($activitiesList as $activity) {
                                        $acts[] = (string) $activity;
                                    }
                                    $value = '{'.implode(',',$acts).'}';
                                }
                            } else if ($column == 'elevation') {
                                 // be sure we get an integer
                                 $value = round($outingxml->elevation);
                            } else if ($column == 'rating') {
                                 // cut too long ratings
                                 $value = strlen($value) > 30 ? substr($value, 0, 30) : $value;
                            } else {
                                $value = $outingxml->$column;
                            }

                            if (isset($value) && !empty($value) && $value != '') {                    
                                if ($status == 1){
                                    // update
                                    $callback = ucfirst($column);
                                    $outingdb[$callback] = $value;
                                } else {
                                    // set
                                    $callback = 'set'.ucfirst($column);
                                    $outingdb->$callback($value);                    
                                }
                            } else {
                                // if column is mandatory and there is no value, throw error
                                if (array_key_exists('notnull', $colDef)
                                    && $colDef['notnull'] == 1) {
                                    //return $this->parseXmlError(14);
                                    $this->logError('xmlError', 14, $column);
                                    $skip = true;
                                }
                            }
                        }
                    }
                    
                    if (!$skip) {
                        // add id (using the authentication id)
                        $outingdb->setSource_id($this->authid);

                        // save outing
                        $outingdb->save();

                        $outingid = $outingdb->getId();

                        $geomError = false;

                        if (isset($outingxml->geom) 
                            && !empty($outingxml->geom) 
                            && $outingxml->geom != '') {
                            $pointCoords = explode(",",$outingxml->geom);
                            if (sizeof($pointCoords) != 2) {
                                //return $this->parseXmlError(16);
                                $this->logError('xmlError', 16);
                                $skip = true;
                                $geomError = true;
                            } else {
                                if (!is_numeric($pointCoords[0]) || !is_numeric($pointCoords[1])) {
                                    //return $this->parseXmlError(17);
                                    $this->logError('xmlError', 17);
                                    $skip = true;
                                    $geomError = true;
                                } else {
                                    // add geometry for summit
                                    $point = "geomfromEWKT('SRID=4326;POINT(".$pointCoords[0]." ".
                                             $pointCoords[1].")')";
                                    $sql = 'UPDATE outings SET geom = '.$point.' WHERE id = '.$outingid.';';

                                    // get regions including the summit coords
                                    $results = sfDoctrine::connection()
                                        ->standaloneQuery($sql)
                                        ->fetchAll();
                                }
                            }
                        }
                        if ($geomError) {
                            $outingid = $outingdb->getId(); // needed to delete the outings<->region relation
                            $outingdb->delete();                    
                        }
                    }
                }
            }

            if ($skip) {
                continue;
            } else {
                // if add or update, set the new outing<->region links
                if ($status < 2) {

                    $bindingError = false;
                  
                    // treat the region data
                    // 3 cases: geom, region_code, region_name
                    if (isset($outingxml->geom) 
                        && !empty($outingxml->geom) 
                        && $outingxml->geom != '') {
                        $case = 1;
                    } else if (isset($outingxml->region_code)
                               && !empty($outingxml->region_code)
                               && $outingxml->region_code != '') {
                        $case = 2;
                    } else if (isset($outingxml->region_name) 
                               && !empty($outingxml->region_name) 
                               && $outingxml->region_name != '') {
                        $case = 3;
                    } else {
                        //return $this->parseXmlError(12);
                        //$this->logError('xmlError', 12);
                        $skip = true;
                        //$bindingError = true;
                    }            
                    if (!$skip) {
                        switch ($case) {
                            case 1 : // geom
                                $this->setRegionFromGeom($outingxml->geom, $outingid, 
                                                         $status); 
                            break;
                            case 2 : // region_code
                                $bindingError = $this->setRegionFromRegionCode($outingxml->region_code, 
                                    $outingid, $status); 
                                /* this is the internal region code, the client must convert
                                his id with the internal id using a conversion table */
                            break;
                            case 3 : // region_name
                                 // region name directly stored with outing
                            break;
                            default:
                                //return $this->parseXmlError(13);
                                $this->logError('xmlError', 13);
                                $bindingError = true;
                        }

                    }
                    // in case of error, remove previously added outing data
                    if ($bindingError) {
                        $this->delOuting($outingid);
                    }
                } else {
                    // delete existing region binding
                   $this->delOutingRegionLink($outingid);
                }
            }
        }        
    }

    /**
     * delete outing
     * @param int $outingid
     */
    private function delOuting($outingid) {
        $item = Doctrine_Query::create()->from('Outing o')->
            where('o.id = ?',$outingid)->execute();
        $item->delete();    
    }

    /**
     * store the outing <-> region link using id
     * @param int $region_code
     * @param int $outingid
     * @param int $status optional
     */
    private function setRegionFromRegionCode($region_code, $outingid, $status = 0) {
        // check if region code exist in database
        $q = new Doctrine_Query();
        $regionids = $q->select('r.id')->from('Region r')->where('r.id = ?', 
            $region_code)->execute();
        
        if (sizeof($regionids) < 1) {
            //return $this->parseXmlError(15);
            $this->logError('xmlError', 15);
            return true;
        }

        // if update delete existing region binding
        if ($status == 1) {
            $this->delOutingRegionLink($outingid);
        }

        // create a new entry in the table outings_regions
        $outingregiondb = new Outing_Region();
        $outingregiondb->setOuting_id($outingid);
        $outingregiondb->setRegion_id($region_code);
        $outingregiondb->save();

        return false;
    }

    /**
     * store the outing <-> region link using geometry
     * @param string $geom
     * @param int $outingid
     * @param int $status optional
     */
    private function setRegionFromGeom($geom, $outingid, $status = 0) {

        // if update delete existing region binding
        if ($status == 1) {
            $this->delOutingRegionLink($outingid);
        }

        $pointCoords = explode(",",$geom);

        $point = "geomfromEWKT('SRID=4326;POINT(".$pointCoords[0]." ".
                  $pointCoords[1].")')";
        $sql = 'SELECT r.id FROM regions r WHERE intersects(r.geom, '.$point.')';
        
        // get regions including the summit coords
        $results = sfDoctrine::connection()
                        ->standaloneQuery($sql)
                        ->fetchAll();

        if (sizeof($results) < 1) {
            //return $this->parseXmlError(18);
            //$this->logError('xmlError', 18);
            return;
        } else {
            foreach ($results as $result) {
                $regionid = $result['id'];
                // create a new entry in the table outings_regions
                $outingregiondb = new Outing_Region();
                $outingregiondb->setOuting_id($outingid);
                $outingregiondb->setRegion_id($regionid);
                $outingregiondb->save();
            }
        }
    }

    /**
     * delete outing <-> region link
     * @param int $outingid
     */
    private function delOutingRegionLink($outingid){
        $item = Doctrine_Query::create()->from('Outing_Region ore')->
                where('ore.outing_id = ?', $outingid)->execute();
        $item->delete();        
    }

    /**
     * convert the activity string (for example {1,4,7} ) into array:
     * @param int $string
     * @return array
     */
    private function stringToArray($string) {
        $string = substr($string, 1, strlen($string) - 2); // removes {}
        return explode(',', $string);    
    }

    /**
     * errors that could happens when reading the xml
     * @param int $errorId
     */
    private function xmlError($errorId) {
        $this->errorSource = 1;
        switch ($errorId) {
            case 0:
                $this->errorMsg = 'content is not xml';
                break;
            case 1:
                $this->errorMsg = 'wrong start tag';
                break;
            case 2:
                $this->errorMsg = 'xml declaration not closed';
                break;
            case 3:
                $this->errorMsg = 'version missing in xml declaration';
                break;
            case 4:
                $this->errorMsg = 'syntax error in xml declaration';
                break;
            case 5:
                $this->errorMsg = 'document must contain at least a well '.
                'formed element';
                break;
            case 6:
                $this->errorMsg = 'element is empty and not closed';
                break;
            case 7:
                $this->errorMsg = 'start tag and end tag mismatch';
                break;
            case 8:
                $this->errorMsg = 'end tag not closed';
                break;
            case 9:
                $this->errorMsg = 'xml sent is not valid xml';
                break;
            case 10:
                $this->errorMsg = 'xml schema file not found';
                break;
            case 11:
                $this->errorMsg = 'xml schema validation failed';
                break;
            case 12:
                $this->errorMsg = 'outing was missing critical data: you '.
                'must set geom+source_id or region_code+source_id or region_name';
                break;
            case 13:
                $this->errorMsg = 'unknown case';
                break;
            case 14:
                $this->errorMsg = 'missing mandatory element';
                break;
            case 15:
                $this->errorMsg = 'region id does not exist';
                break;
            case 16:
                $this->errorMsg = 'summit coordinate syntax incorrect. '.
                'It must be <geom>lat,lon</geom>';
                break;
            case 17:
                $this->errorMsg = 'summit coordinates are not valid values';
                break;
            case 18:
                $this->errorMsg = 'summit coordinates are outside any '.
                'existing regions';
                break;
            case 19:
                $this->errorMsg = 'An outing with the same original_outing_id'.
                ' already exists for the selected system';
                break;
            default:
                // unidentified error
                $this->errorMsg = 'unidentified error';
        }
    }

    /**
     * errors that could happens when authenticating
     * @param int $errorId
     */
    private function authError($errorId) {
        $this->errorSource = 2;
        switch ($errorId) {
            case 0:
                $this->errorMsg = 'authentication failed';
                break;
            default:
                // unidentified error
                $this->errorMsg = 'unidentified error';
        }
    }

    /**
     * errors that could happens when authenticating
     * @param int $errorId
     */
    private function dbError($errorId) {
        $this->errorSource = 3;
        switch ($errorId) {
            case 1:
                $this->errorMsg = 'outing id not set';
                break;
            case 2:
                $this->errorMsg = 'no outing exist with that id or you are '.
                'not the owner of that outing';
                break;
            default:
                // unidentified error
                $this->errorMsg = 'unidentified error';
        }
    }

    /**
     * errors that could happens when querying
     * @param int $errorId
     */
    private function queryError($errorId) {
        $this->errorSource = 4;
        switch ($errorId) {
            case 0:
                $this->errorMsg = 'malformed date, date should be '.
                'yyyy-mm-day';
                break;
            case 1:
                $this->errorMsg = 'orderby field does not exist, check '.
                'spelling';
                break;
            case 2:
                $this->errorMsg = 'no parameter received, check spelling';
                break;
            case 3:
                $this->errorMsg = 'no values received for parameter';
                break;
            default:
                // unidentified error
                $this->errorMsg = 'unidentified error';
        }
    }
    /**
    * call error functions and store error for feedback
    * @param string callback
    * @param int errorcode
    */
    private function logError($callback, $errorcode, $extra = null) {
        $this->error = true;
        $this->$callback($errorcode);
        // store error
        $e = new outingError();
        $e->idcount = $this->idcount;
        $e->id = $this->currentOutingId;
        $e->errorMsg = $this->errorMsg . ($extra ? ' ('.$extra.')' : '');
        $this->errorStack[] = $e;
    }

    /**
     * generate an xml feedback message
     * 1 = ok
     * 0 = error
     */
    private function feedback() {
        $feedback = '<metaengine_result/>';
        $xml = new SimpleXMLElement($feedback);
        // 0 = error occured, 1 =  ok
        $status = $this->error ? 0 : 1;
        $xstatus = $xml->addChild('status', $status);
        if ($this->error) {
            $xerrs = $xml->addChild('errors');
            foreach ($this->errorStack as $e) {
                $xerr = $xerrs->addChild('error');
                $xerroutidcount = $xerr->addChild('outing_counter', $e->idcount);
                $xerroutid = $xerr->addChild('outing_id', $e->id);
                $xerrmsg = $xerr->addChild('error_message', $e->errorMsg);
            }
        }
        $this->feedbackResult = $xml->asXML();
    }
}

class outingError {
    /**
    * outing internal id (to count the outing being added at once)
    */
    public $idcount;

    /**
    * outing id
    */
    public $id;
    /**
    * error Message
    */
    public $errorMsg;
}
