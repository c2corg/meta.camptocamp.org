<?php
/*
 *  $Id: UnitOfWork.php 1947 2007-07-06 21:18:36Z gnat $
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
Doctrine::autoload('Doctrine_Connection_Module');
/**
 * Doctrine_Connection_UnitOfWork
 *
 * @package     Doctrine
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision: 1947 $
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
class Doctrine_Connection_UnitOfWork extends Doctrine_Connection_Module
{
    /**
     * buildFlushTree
     * builds a flush tree that is used in transactions
     *
     * The returned array has all the initialized components in
     * 'correct' order. Basically this means that the records of those
     * components can be saved safely in the order specified by the returned array.
     *
     * @param array $tables     an array of Doctrine_Table objects or component names
     * @return array            an array of component names in flushing order
     */
    public function buildFlushTree(array $tables)
    {
        $tree = array();
        foreach ($tables as $k => $table) {

            if ( ! ($table instanceof Doctrine_Table)) {
                $table = $this->conn->getTable($table, false);
            }
            $nm     = $table->getComponentName();

            $index  = array_search($nm, $tree);

            if ($index === false) {
                $tree[] = $nm;
                $index  = max(array_keys($tree));
            }

            $rels = $table->getRelations();

            // group relations

            foreach ($rels as $key => $rel) {
                if ($rel instanceof Doctrine_Relation_ForeignKey) {
                    unset($rels[$key]);
                    array_unshift($rels, $rel);
                }
            }

            foreach ($rels as $rel) {
                $name   = $rel->getTable()->getComponentName();
                $index2 = array_search($name,$tree);
                $type   = $rel->getType();

                // skip self-referenced relations
                if ($name === $nm) {
                    continue;
                }

                if ($rel instanceof Doctrine_Relation_ForeignKey) {
                    if ($index2 !== false) {
                        if ($index2 >= $index)
                            continue;

                        unset($tree[$index]);
                        array_splice($tree,$index2,0,$nm);
                        $index = $index2;
                    } else {
                        $tree[] = $name;
                    }

                } elseif ($rel instanceof Doctrine_Relation_LocalKey) {
                    if ($index2 !== false) {
                        if ($index2 <= $index)
                            continue;

                        unset($tree[$index2]);
                        array_splice($tree,$index,0,$name);
                    } else {
                        array_unshift($tree,$name);
                        $index++;
                    }
                } elseif ($rel instanceof Doctrine_Relation_Association) {
                    $t = $rel->getAssociationFactory();
                    $n = $t->getComponentName();

                    if ($index2 !== false)
                        unset($tree[$index2]);

                    array_splice($tree, $index, 0, $name);
                    $index++;

                    $index3 = array_search($n, $tree);

                    if ($index3 !== false) {
                        if ($index3 >= $index)
                            continue;

                        unset($tree[$index]);
                        array_splice($tree, $index3, 0, $n);
                        $index = $index2;
                    } else {
                        $tree[] = $n;
                    }
                }
            }
        }
        return array_values($tree);
    }
    /**
     * saves the given record
     *
     * @param Doctrine_Record $record
     * @return void
     */
    public function saveGraph(Doctrine_Record $record)
    {
    	$conn = $this->getConnection();
        
        if ($conn->transaction->isSaved($record)) {
            return false;
        }

        $conn->transaction->addSaved($record);

        $conn->beginTransaction();

        $saveLater = $this->saveRelated($record);

        if ($record->isValid()) {
            $this->save($record);
        } else {
            $conn->transaction->addInvalid($record);
        }

        foreach ($saveLater as $fk) {
            $alias = $fk->getAlias();

            if ($record->hasReference($alias)) {
                $obj = $record->$alias;
                $obj->save($conn);
            }
        }

        // save the MANY-TO-MANY associations
        $this->saveAssociations($record);

        $conn->commit();
        
        return true;
    }
    /**
     * saves the given record
     *
     * @param Doctrine_Record $record
     * @return void
     */
    public function save(Doctrine_Record $record)
    {
        $event = new Doctrine_Event($this, Doctrine_Event::RECORD_SAVE);

        $record->preSave($event);

        if ( ! $event->skipOperation) {
            switch ($record->state()) {
                case Doctrine_Record::STATE_TDIRTY:
                    $this->insert($record);
                    break;
                case Doctrine_Record::STATE_DIRTY:
                case Doctrine_Record::STATE_PROXY:
                    $this->update($record);
                    break;
                case Doctrine_Record::STATE_CLEAN:
                case Doctrine_Record::STATE_TCLEAN:
                    // do nothing
                    break;
            }
        }

        $record->postSave($event);
    }
    /**
     * deletes this data access object and all the related composites
     * this operation is isolated by a transaction
     *
     * this event can be listened by the onPreDelete and onDelete listeners
     *
     * @return boolean      true on success, false on failure
     */
    public function delete(Doctrine_Record $record)
    {
        if ( ! $record->exists()) {
            return false;
        }
        $this->conn->beginTransaction();

        $event = new Doctrine_Event($this, Doctrine_Event::RECORD_DELETE);

        $record->preDelete($event);
        
        $record->state(Doctrine_Record::STATE_LOCKED);

        $this->deleteComposites($record);
        
        $record->state(Doctrine_Record::STATE_TDIRTY);

        if ( ! $event->skipOperation) {
            $this->conn->transaction->addDelete($record);

            $record->state(Doctrine_Record::STATE_TCLEAN);
        }
        $record->postDelete($event);

        $this->conn->commit();

        return true;
    }

    /**
     * saveRelated
     * saves all related records to $record
     *
     * @throws PDOException         if something went wrong at database level
     * @param Doctrine_Record $record
     */
    public function saveRelated(Doctrine_Record $record)
    {
        $saveLater = array();
        foreach ($record->getReferences() as $k => $v) {
            $rel = $record->getTable()->getRelation($k);

            if ($rel instanceof Doctrine_Relation_ForeignKey ||
                $rel instanceof Doctrine_Relation_LocalKey) {
                $local = $rel->getLocal();
                $foreign = $rel->getForeign();

                if ($record->getTable()->hasPrimaryKey($rel->getLocal())) {
                    if ( ! $record->exists()) {
                        $saveLater[$k] = $rel;
                    } else {
                        $v->save($this->conn);
                    }
                } else {
                    // ONE-TO-ONE relationship
                    $obj = $record->get($rel->getAlias());

                    // Protection against infinite function recursion before attempting to save
                    if ($obj instanceof Doctrine_Record &&
                        $obj->isModified()) {
                        $obj->save($this->conn);
                    }
                }
            }
        }

        return $saveLater;
    }
    /**
     * saveAssociations
     *
     * this method takes a diff of one-to-many / many-to-many original and
     * current collections and applies the changes
     *
     * for example if original many-to-many related collection has records with
     * primary keys 1,2 and 3 and the new collection has records with primary keys
     * 3, 4 and 5, this method would first destroy the associations to 1 and 2 and then
     * save new associations to 4 and 5
     *
     * @throws PDOException         if something went wrong at database level
     * @param Doctrine_Record $record
     * @return void
     */
    public function saveAssociations(Doctrine_Record $record)
    {
        foreach ($record->getReferences() as $k => $v) {
            $rel = $record->getTable()->getRelation($k);
            
            if ($rel instanceof Doctrine_Relation_Association) {   
                $v->save($this->conn);

                $assocTable = $rel->getAssociationTable();
                foreach ($v->getDeleteDiff() as $r) {
                    $query = 'DELETE FROM ' . $assocTable->getTableName()
                           . ' WHERE ' . $rel->getForeign() . ' = ?'
                           . ' AND ' . $rel->getLocal() . ' = ?';

                    $this->conn->execute($query, array($r->getIncremented(), $record->getIncremented()));
                }
                foreach ($v->getInsertDiff() as $r) {
                    $assocRecord = $assocTable->create();
                    $assocRecord->set($rel->getForeign(), $r);
                    $assocRecord->set($rel->getLocal(), $record);
                    $assocRecord->save($this->conn);
                }
            }
        }
    }
    /**
     * deletes all related composites
     * this method is always called internally when a record is deleted
     *
     * @throws PDOException         if something went wrong at database level
     * @return void
     */
    public function deleteComposites(Doctrine_Record $record)
    {
        foreach ($record->getTable()->getRelations() as $fk) {
            switch ($fk->getType()) {
                case Doctrine_Relation::ONE_COMPOSITE:
                case Doctrine_Relation::MANY_COMPOSITE:
                    $obj = $record->get($fk->getAlias());
                    if ( $obj instanceof Doctrine_Record && 
                           $obj->state() != Doctrine_Record::STATE_LOCKED)  {
                            
                            $obj->delete($this->conn);
                           	
                    }
                    break;
            }
        }
    }
    /**
     * saveAll
     * persists all the pending records from all tables
     *
     * @throws PDOException         if something went wrong at database level
     * @return void
     */
    public function saveAll()
    {
        // get the flush tree
        $tree = $this->buildFlushTree($this->conn->getTables());

        // save all records
        foreach ($tree as $name) {
            $table = $this->conn->getTable($name);

            foreach ($table->getRepository() as $record) {
                $this->save($record);
            }
        }

        // save all associations
        foreach ($tree as $name) {
            $table = $this->conn->getTable($name);

            foreach ($table->getRepository() as $record) {
                $this->saveAssociations($record);
            }
        }
    }
    /**
     * update
     * updates the given record
     *
     * @param Doctrine_Record $record   record to be updated
     * @return boolean                  whether or not the update was successful
     */
    public function update(Doctrine_Record $record)
    {
        $event = new Doctrine_Event($this, Doctrine_Event::RECORD_UPDATE);
        
        $record->preUpdate($event);

        if ( ! $event->skipOperation) {
            $array = $record->getPrepared();
    
            if (empty($array)) {
                return false;
            }
            $set = array();
            foreach ($array as $name => $value) {
                if ($value instanceof Doctrine_Expression) {
                    $set[] = $value->getSql();
                    unset($array[$name]);
                } else {

                    $set[] = $name . ' = ?';
    
                    if ($value instanceof Doctrine_Record) {
                        if ( ! $value->exists()) {
                            $record->save($this->conn);
                        }
                        $array[$name] = $value->getIncremented();
                        $record->set($name, $value->getIncremented());
                    }
                }
            }
    
            $params = array_values($array);
            $id     = $record->identifier();
    
            if ( ! is_array($id)) {
                $id = array($id);
            }
            $id     = array_values($id);
            $params = array_merge($params, $id);
    
            $sql  = 'UPDATE ' . $this->conn->quoteIdentifier($record->getTable()->getTableName())
                  . ' SET ' . implode(', ', $set)
                  . ' WHERE ' . implode(' = ? AND ', $record->getTable()->getPrimaryKeys())
                  . ' = ?';
    
            $stmt = $this->conn->getDbh()->prepare($sql);
            $stmt->execute($params);
    
            $record->assignIdentifier(true);
        }
        $record->postUpdate($event);

        return true;
    }
    /**
     * inserts a record into database
     *
     * @param Doctrine_Record $record   record to be inserted
     * @return boolean
     */
    public function insert(Doctrine_Record $record)
    {
         // listen the onPreInsert event
        $event = new Doctrine_Event($this, Doctrine_Event::RECORD_INSERT);

        $record->preInsert($event);
        
        if ( ! $event->skipOperation) {
            $array = $record->getPrepared();
    
            if (empty($array)) {
                return false;
            }
            $table     = $record->getTable();
            $keys      = $table->getPrimaryKeys();
    
            $seq       = $record->getTable()->sequenceName;
    
            if ( ! empty($seq)) {
                $id             = $this->conn->sequence->nextId($seq);
                $name           = $record->getTable()->getIdentifier();
                $array[$name]   = $id;
    
                $record->assignIdentifier($id);
            }
    
            $this->conn->insert($table->getTableName(), $array);
    
            if (empty($seq) && count($keys) == 1 && $keys[0] == $table->getIdentifier() &&
                $table->getIdentifierType() != Doctrine::IDENTIFIER_NATURAL) {
    
                if (strtolower($this->conn->getName()) == 'pgsql') {
                    $seq = $table->getTableName() . '_' . $keys[0];
                }
    
                $id = $this->conn->sequence->lastInsertId($seq);
    
                if ( ! $id) {
                    $id = $table->getMaxIdentifier();
                }
    
                $record->assignIdentifier($id);
            } else {
                $record->assignIdentifier(true);
            }
        }
        $record->getTable()->addRecord($record);

        $record->postInsert($event);

        return true;
    }
}
