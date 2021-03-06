<?php

namespace Admingenerator\GeneratorBundle\Guesser;

use Admingenerator\GeneratorBundle\Exception\NotImplementedException;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\DependencyInjection\ContainerAware;

class DoctrineODMFieldGuesser extends ContainerAware
{
    private $documentManager;

    private $metadata;

    private static $current_class;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    protected function getMetadatas($class = null)
    {
        if ($class) {
            self::$current_class = $class;
        }

        if (isset($this->metadata[self::$current_class]) || !$class) {
            return $this->metadata[self::$current_class];
        }

        if (!$this->documentManager->getConfiguration()->getMetadataDriverImpl()->isTransient($class)) {
            $this->metadata[$class] = $this->documentManager->getClassMetadata($class);
        }

        return $this->metadata[$class];
    }

    public function getAllFields($class)
    {
        $fields = array();

        foreach ($this->getMetadatas($class)->fieldMappings as $fieldName => $metadatas) {
            if (!$this->getMetadatas($class)->hasAssociation($fieldName)) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }

    public function getDbType($class, $fieldName)
    {
        $metadata = $this->getMetadatas($class);

        if ($metadata->hasAssociation($fieldName)) {
            if ($metadata->isSingleValuedAssociation($fieldName)) {
                return 'document';
            } else {
                return 'collection';
            }
        }

        if ($metadata->hasField($fieldName)) {
          $mapping = $metadata->getFieldMapping($fieldName);

          return $mapping['type'];
        }

        return 'virtual';

        //return $metadata->getTypeOfField($fieldName);//Not Yet implemented by doctrine
    }

    public function getFormType($dbType, $columnName)
    {
        switch ($dbType) {
            case 'boolean':
                return 'checkbox';
            case 'datetime':
            case 'timestamp':
            case 'vardatetime':
            case 'datetimetz':
                return 'datetime';
            case 'date':
                return 'datetime';
                break;
            case 'decimal':
            case 'float':
                return 'number';
                break;
            case 'int':
            case 'integer':
            case 'int_id':
            case 'bigint':
            case 'smallint':
                return 'integer';
                break;
            case 'id':
            case 'custom_id':
            case 'string':
                return 'text';
                break;
            case 'text':
                return 'textarea';
                break;
            case 'time':
                return 'time';
                break;
            case 'document':
                return 'document';
                break;
             case 'collection':
                return 'double_list';
                break;
            case 'hash':
                return 'collection';
                break;
            case 'virtual':
                throw new NotImplementedException('The dbType "'.$dbType.'" is only for list implemented (column "'.$columnName.'") ');
                break;
            default:
                throw new NotImplementedException('The dbType "'.$dbType.'" is not yet implemented (column "'.$columnName.'")');
                break;
        }
    }

    public function getFilterType($dbType, $columnName)
    {
         switch ($dbType) {
             case 'hash':
             case 'text':
                return 'text';
                break;
             case 'boolean':
                return 'choice';
                break;
             case 'datetime':
             case 'vardatetime':
             case 'datetimetz':
             case 'date':
                return 'datepicker_range';
                break;
             case 'collection':
                return 'document';
                break;
         }

         return $this->getFormType($dbType, $columnName);
    }

    public function getFormOptions($formType, $dbType, $columnName)
    {
        if ('boolean' == $dbType) {
            return array('required' => false);
        }

        if ('document' == $dbType) {
            $mapping = $this->getMetadatas()->getFieldMapping($columnName);

            return array( 'class' => $mapping['targetDocument'], 'multiple' => false);
        }

        if ('collection' == $dbType) {
            $mapping = $this->getMetadatas()->getFieldMapping($columnName);

            return array('class' => isset($mapping['targetDocument']) ? $mapping['targetDocument'] : null);
        }

        if ('collection' == $formType) {
            return array('allow_add' => true, 'allow_delete' => true);
        }

        return array('required' => $this->isRequired($columnName));
    }

    protected function isRequired($fieldName)
    {
        if ($this->getMetadatas()->hasField($fieldName) &&
            (!$this->getMetadatas()->hasAssociation($fieldName) || $this->getMetadatas()->isSingleValuedAssociation($fieldName))) {
            return !$this->getMetadatas()->isNullable($fieldName);
        }

        return false;
    }

    public function getFilterOptions($formType, $dbType, $ColumnName)
    {
        $options = array('required' => false);

        if ('boolean' == $dbType) {
            $options['choices'] = array(
               0 => $this->container->get('translator')->trans('boolean.no', array(), 'Admingenerator'),
               1 => $this->container->get('translator')->trans('boolean.yes', array(), 'Admingenerator')
            );
            $options['empty_value'] = $this->container->get('translator')->trans('boolean.yes_or_no', array(), 'Admingenerator');
        }

        if ('document' == $dbType) {
            return array_merge($this->getFormOptions($formType, $dbType, $ColumnName), $options);
        }

        if ('collection' == $formType) {
            return array('allow_add' => true, 'allow_delete' => true, 'by_reference' => true);
        }

        if ('collection' == $dbType) {
            return array_merge($this->getFormOptions($formType, $dbType, $ColumnName), $options, array('multiple'=>false));
        }

        return $options;
    }

    /**
     * Find the pk name
     */
    public function getModelPrimaryKeyName($class = null)
    {
        return $this->getMetadatas($class)->getIdentifier();
    }
}
