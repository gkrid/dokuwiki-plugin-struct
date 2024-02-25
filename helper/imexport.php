<?php

/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\SchemaImporter;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\Schema;

class helper_plugin_struct_imexport extends Plugin
{
    private $sqlite;

    /**
     * this possibly duplicates @see helper_plugin_struct::getSchema()
     */
    public function getAllSchemasList()
    {
        return Schema::getAll();
    }

    /**
     * Delete all existing assignment patterns of a schema and replace them with the provided ones.
     *
     * @param string $schemaName
     * @param string[] $patterns
     */
    public function replaceSchemaAssignmentPatterns($schemaName, $patterns)
    {
        /** @var \helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        $this->sqlite = $helper->getDB(false);
        if (!$this->sqlite instanceof SQLiteDB) return;

        $schemaName = $this->sqlite->escape_string($schemaName);
        $sql = [];
        $sql[] = "DELETE FROM schema_assignments_patterns WHERE tbl = '$schemaName'";
        $sql[] = "DELETE FROM schema_assignments WHERE tbl = '$schemaName'";
        foreach ($patterns as $pattern) {
            $pattern = $this->sqlite->escape_string($pattern);
            $sql[] = "INSERT INTO schema_assignments_patterns (pattern, tbl) VALUES ('$pattern','$schemaName')";
        }

        $this->sqlite->doTransaction($sql);
        $assignments = Assignments::getInstance();
        $assignments->propagatePageAssignments($schemaName);
    }

    /**
     * Returns array of patterns for the given Schema
     *
     * @param string $schemaName
     * @return string[]
     */
    public function getSchemaAssignmentPatterns($schemaName)
    {
        /** @var \helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        $this->sqlite = $helper->getDB(false);
        if (!$this->sqlite instanceof SQLiteDB) return [];

        $sql = 'SELECT pattern FROM schema_assignments_patterns WHERE tbl = ?';
        $patterns = $this->sqlite->queryAll($sql, $schemaName);
        return array_map(static fn($elem) => $elem['pattern'], $patterns);
    }

    /**
     * Get the json of the current version of the given schema or false if the schema doesn't exist.
     *
     * @param string $schemaName
     * @return string|bool The json string or false if the schema doesn't exist
     */
    public function getCurrentSchemaJSON($schemaName)
    {
        $schema = new Schema($schemaName);
        if ($schema->getId() == 0) {
            return false;
        }
        return $schema->toJSON();
    }

    /**
     * Import a schema. If a schema with the given name already exists, then it will be overwritten. Otherwise a new
     * schema will be created.
     *
     * @param string $schemaName The name of the schema
     * @param string $schemaJSON The structure of the schema as exportet by structs export functionality
     * @param string $user optional, the user that should be set in the schemas history.
     *                      If blank, the current user is used.
     * @return bool|int the id of the new schema version or false on error.
     *
     * @throws StructException
     */
    public function importSchema($schemaName, $schemaJSON, $user = null)
    {
        $importer = new SchemaImporter($schemaName, $schemaJSON);
        if (!blank($user)) {
            $importer->setUser($user);
        }
        $ok = $importer->build();
        return $ok;
    }
}
