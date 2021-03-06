<?php
/*
 *  $Id$
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
 * Doctrine_AuditLog_Listener
 *
 * @package     Doctrine
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
class Doctrine_AuditLog_Listener extends Doctrine_EventListener
{
    
    protected $_auditLog;

    public function __construct(Doctrine_AuditLog $auditLog) {
        $this->_auditLog = $auditLog;
    }
    public function onPreInsert(Doctrine_Record $record)
    {
    	$versionColumn = $this->_auditLog->getOption('versionColumn');

        $record->set($versionColumn, 1);
    }
    public function onPreDelete(Doctrine_Record $record)
    {
    	$versionColumn = $this->_auditLog->getOption('versionColumn');
    	$version = $record->get($versionColumn);

        $record->set($versionColumn, ++$version);
    }
    public function onPreUpdate(Doctrine_Record $record)
    {
    	$versionColumn = $this->_auditLog->getOption('versionColumn');
    	$version = $record->get($versionColumn);

        $record->set($versionColumn, ++$version);
    }
}
