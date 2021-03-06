<?php
/*
 *  $Id: Db.php 1901 2007-06-29 09:39:03Z zYne $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.com>.
 */

/**
 * Doctrine_Cache_Sqlite
 *
 * @package     Doctrine
 * @subpackage  Doctrine_Cache
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision: 1901 $
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
class Doctrine_Cache_Db extends Doctrine_Cache_Driver implements Countable
{
    /**
     * constructor
     *
     * @param array $_options      an array of options
     */
    public function __construct($options) 
    {
    	if ( ! isset($options['connection']) || 
             ! ($options['connection'] instanceof Doctrine_Connection)) {

    	    throw new Doctrine_Cache_Exception('Connection option not set.');
    	}

        $this->_options = $options;
    }
    /**
     * getConnection
     * returns the connection object associated with this cache driver
     *
     * @return Doctrine_Connection      connection object
     */
    public function getConnection() 
    {
        return $this->_options['connection'];
    }
    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * Note : return value is always "string" (unserialization is done by the core not by the backend)
     *
     * @param string $id cache id
     * @param boolean $testCacheValidity        if set to false, the cache validity won't be tested
     * @return string cached datas (or false)
     */
    public function fetch($id, $testCacheValidity = true)
    {
        $sql    = 'SELECT data, expires FROM cache WHERE id = ?';

        if ($testCacheValidity) {
            $sql .= ' AND (expire=0 OR expire > ' . time() . ')';
        }

        $result = $this->getConnection()->fetchAssoc($sql, array($id));

        return unserialize($result['data']);
    }
    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param string $id cache id
     * @return mixed false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    public function contains($id) 
    {
        $sql = 'SELECT expires FROM cache WHERE id = ? AND (expire=0 OR expire > ' . time() . ')';

        return $this->getConnection()->fetchOne($sql, array($id));
    }
    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always saved as a string
     *
     * @param string $data      data to cache
     * @param string $id        cache id
     * @param int $lifeTime     if != false, set a specific lifetime for this cache record (null => infinite lifeTime)
     * @return boolean true if no problem
     */
    public function save($data, $id, $lifeTime = false)
    {
        $sql = 'INSERT INTO cache (id, data, expires) VALUES (?, ?, ?)';

        $params = array($id, serialize($data), (time() + $lifeTime));

        return (bool) $this->getConnection()->exec($sql, $params);
    }
    /**
     * Remove a cache record
     * 
     * @param string $id cache id
     * @return boolean true if no problem
     */
    public function delete($id) 
    {
        $sql = 'DELETE FROM cache WHERE id = ?';

        return (bool) $this->getConnection()->exec($sql, array($id));
    }
    /**
     * count
     * returns the number of cached elements
     *
     * @return integer
     */
    public function count()
    {
        return (int) $this->getConnection()->fetchOne('SELECT COUNT(*) FROM cache');
    }
}
