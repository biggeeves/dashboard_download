<?php

namespace DCC\RecordStatusDownload;

use Exception;
use ExternalModules\AbstractExternalModule;
use phpDocumentor\Reflection\Types\String_;
use REDCap;

/**
 * Class DictionaryHelper
 * @package DCC\RecordStatusDownload
 */
class DictionaryHelper extends AbstractExternalModule
{
    /**
     * @var array|bool|mixed
     */
    private $eventNames;
    /**
     * @var array|null
     */
    private $completedInstrumentVars;
    /**
     * @var array|false|mixed|string|null
     */
    private $eventNameLabels;
    /**
     * @var array|null
     */
    private $completedFields;

    /**
     * @var string[]
     */
    private $completedValues = [
        0 => '0',
        1 => '1',
        2 => '2'
    ];
    /**
     * @var array|null
     */
    private $eventGrid;

    /**
     * @var |null
     */
    private $instrumentNames;

    /**
     * @var |null
     */
    private $record_id_field;

    /**
     * @var String $pid Project id of current REDCap project.
     */
    protected $pid;

    /**
     * @var mixed|null
     */
    private $userRights;
    /**
     * @var mixed|null
     */
    private $group_id;
    /**
     * @var mixed
     */
    private $rights;
    /**
     * @var mixed|string|null
     */
    private $user;
    /**
     * @var |null
     */
    private $dataDictionaryJSON;
    /**
     * @var false|null
     */
    private $isLongitudinal;
    /**
     * @var array|string|string[]
     */
    private $dataDictionary;
    /**
     * @var array|null
     */
    private $fieldNames;
    /**
     * @var |null
     */
    private $sectionHeader;
    /**
     * @var |null
     */
    private $fieldType;
    /**
     * @var |null
     */
    private $fieldLabel;
    /**
     * @var |null
     */
    private $selectChoicesCalculations;
    /**
     * @var |null
     */
    private $choiceLabels;
    /**
     * @var |null
     */
    private $fieldNote;
    /**
     * @var |null
     */
    private $fieldValidation;
    /**
     * @var |null
     */
    private $fieldMax;
    /**
     * @var |null
     */
    private $fieldMin;
    /**
     * @var |null
     */
    private $fieldCheckBox;
    /**
     * @var |null
     */
    private $dictionary;
    /**
     * @var array|null
     */
    private $fieldsByInstrument;
    /**
     * @var |null
     */
    private $identifier;
    /**
     * @var |null
     */
    private $branchingLogic;
    /**
     * @var |null
     */
    private $requiredField;
    /**
     * @var |null
     */
    private $qustionNumber;
    /**
     * @var |null
     */
    private $fieldAnnotation;
    /**
     * @var |null
     */
    private $matrixGroupName;
    /**
     * @var |null
     */
    private $customAlignment;
    /**
     * @var |null
     */
    private $questionNumber;
    /**
     * @var |null
     */
    private $matrixGroup;
    /**
     * @var |null
     */
    private $projectTitle;

    /**
     * Initialize class variables.
     * @param $pid  integer if $pid is null than use the current project id.
     */
    public function __construct($pid = null)
    {
        global $project_id;
        parent::__construct();
        if ($pid) {
            $this->pid = $pid;
        } else {
            $this->pid = $project_id;
        }
        if (is_null($this->pid)) {
            return;
        }
        echo $this->pid . "x<br>";
        $this->setDataDictionaryArray();
        $this->setDataDictionaryJSON();
        $this->parseDataDictionary();
        $this->setIsLongitudinal();
        $this->instrumentNames = REDCap::getInstrumentNames();
        $this->rights = REDCap::getUserRights($this->user);
        $this->setUserInfo();
        $this->setEventNames();
        $this->setEventNamelabels();
        $this->setInstrumentCompleteVar($this->instrumentNames);
        $this->setEventGrid();
        $this->setProjectTitle();
    }

    private function setProjectTitle()
    {
        $this->projectTitle = REDCap::getProjectTitle();
    }

    /**
     *
     */
    private function setDataDictionaryArray()
    {
        try {
            $this->dataDictionary = REDCap::getDataDictionary($this->pid, 'array');
        } catch (Exception $e) {
        }
    }

    public function limitUserToInstruments()
    {
        foreach ($this->userRights['forms'] as $instrumentName => $right) {
            if (intval($right) === 0) {
                unset($this->instrumentNames[$instrumentName]);
            }
        }
    }

    private function setUserInfo()
    {
        $this->user = USERID;
        $this->rights = REDCap::getUserRights($this->user);
        $this->group_id = $this->rights[$this->user]['group_id'];
        $this->userRights = array_shift($this->rights);
    }

    /**
     * Set eventNames using REDCap method.
     */
    private function setEventNames()
    {
        $this->eventNames = REDCap::getEventNames(true, false);
    }

    /**
     * Set eventLabels using REDCap method.
     */
    private function setEventNameLabels()
    {
        $this->eventNameLabels = REDCap::getEventNames(false, false);
    }

    /**
     * Creates a list of autogenerated REDCap variables for each instrument Completed status.
     *
     */
    private function setInstrumentCompleteVar()
    {
        $this->completedInstrumentVars = [];
        $this->completedFields = [];
        foreach ($this->instrumentNames as $shortName => $longName) {
            $this->completedInstrumentVars[$shortName] = $shortName . '_complete';
            $this->completedFields[] = $shortName . '_complete';
        }
    }

    /**
     * Create a 2D array of events and if the instrument is in that event.
     * @return null if not longitudinal
     */
    private function setIsLongitudinal()
    {
        $this->isLongitudinal = false;
        if (REDCap::isLongitudinal()) {
            $this->isLongitudinal = true;
        }
    }

    /**
     *
     */
    private function parseDataDictionary()
    {
        $this->fieldsByInstrument = [];
        $this->fieldNames = [];
        foreach ($this->dataDictionary as $fieldName => $properties) {
            $this->fieldNames[] = $fieldName;
            foreach ($properties as $property => $value) {
                if ($value === '') continue;  // does not have a value
                switch ($property) {
                    case 'form_name':
                        $this->fieldsByInstrument[$value][] = $fieldName;
                        break;
                    case 'section_header':
                        $this->sectionHeader[$fieldName] = $value;
                        break;
                    case 'field_type':
                        $this->fieldType[$fieldName] = $value;
                        break;

                    case 'field_label':
                        $this->fieldLabel[$fieldName] = $value;
                        break;

                    case 'select_choices_or_calculations':
                        $this->selectChoicesCalculations[$fieldName] = $value;
                        $this->choiceLabels[$fieldName] = $this->makeChoiceLabels($value);
                        break;

                    case 'field_note':
                        $this->fieldNote[$fieldName] = $value;
                        break;
                    case 'text_validation_type_or_show_slider_number':
                        $this->fieldValidation[$fieldName] = $value;
                        break;

                    case 'text_validation_max':
                        $this->fieldMax[$fieldName] = $value;
                        break;

                    case 'text_validation_min':
                        $this->fieldMin[$fieldName] = $value;
                        break;

                    case 'identifier':
                        $this->identifier[$fieldName] = $value;
                        break;

                    case 'branching_logic':
                        $this->branchingLogic[$fieldName] = $value;
                        break;

                    case 'required_field':
                        $this->requiredField[$fieldName] = $value;
                        break;

                    case 'custom_alignment':
                        $this->customAlignment[$fieldName] = $value;
                        break;

                    case 'question_number':
                        $this->questionNumber[$fieldName] = $value;
                        break;

                    case 'matrix_group_name':
                        $this->matrixGroupName[$fieldName] = $value;
                        $this->matrixGroup[$value][] = $fieldName;
                        break;

                    case 'field_annotation':
                        $this->fieldAnnotation[$fieldName] = $value;
                        break;

                }
                if ($property == 'field_type') {
                    if ($value == 'yesno') {
                        $this->choiceLabels[$fieldName] = $this->makeChoiceLabels('0, No|1, Yes');
                    } elseif ($value == 'checkbox') {
                        $this->fieldCheckBox[$fieldName] = 1;
                    }
                }
            }
        }
    }

    /**
     * @param $optionText
     * @return array
     */
    private function makeChoiceLabels($optionText)
    {
        $choices = explode('|', $optionText);
        $choicesById = [];
        foreach ($choices as $choice) {
            $parts = explode(',', $choice);
            $choice = trim($parts[0]);
            $label = trim($parts[1]);
            $choicesById[$choice] = $label;
        }
        return $choicesById;
    }

    public function getFieldProperties($fieldName)
    {
        if (!in_array($fieldName, $this->fieldNames)) {
            return null;
        }
        return $this->dataDictionary[$fieldName];
    }

    /**
     * Create a 2D array of events and if the instrument is in that event.
     * @return null if not longitudinal
     */
    private function setEventGrid()
    {
        // Check if project is longitudinal first
        if (!REDCap::isLongitudinal()) {
            return null;
        }

        $this->eventGrid = [];
        foreach ($this->eventNames as $eventId => $eventName) {
            try {
                $allFieldsByEvent = REDCap::getValidFieldsByEvents($this->pid, $eventId);
            } catch (Exception $e) {
                die ('Error: The project ID was not set yet the project is longitudinal.');
            }
            foreach ($this->completedInstrumentVars as $shortName => $complete) {
                $this->eventGrid[$eventId][$shortName] = false;
                if (in_array($complete, $allFieldsByEvent)) {
                    $this->eventGrid[$eventId][$shortName] = true;
                }
            }
        }
        return true;
    }

    /**
     * Sets dataDictionaryJSON
     * @param null $project_id
     * @return null
     * @throws Exception
     * @sets
     */
    private function setDataDictionaryJSON($project_id = null)
    {
        if (is_null($project_id)) {
            return null;
        }
        $this->dataDictionaryJSON = REDCap::getDataDictionary($project_id, 'json');
    }

    public function getInstrumentNames()
    {
        return $this->instrumentNames;
    }


    public function getCompletedInstrumentVars()
    {
        return $this->completedInstrumentVars;
    }

    public function getCompletedFields()
    {
        return $this->completedFields;
    }


    public function getEventGrid()
    {
        return $this->eventGrid;
    }

    public function getEventNameLabels()
    {
        return $this->eventNameLabels;
    }

    public function getUserRights()
    {
        return $this->userRights;
    }

    public function getEventNames()
    {
        return $this->eventNames;
    }

    /**
     * @return string[] Data Dictionary as JSON
     */
    public function getDataDictionaryJSON()
    {
        return $this->dataDictionaryJSON;
    }

    public function getIsLongitudinal()
    {
        return $this->isLongitudinal;
    }

    public function getFieldNames()
    {
        return $this->fieldNames;
    }

    public function getFieldTypes()
    {
        return $this->fieldType;
    }


    public function getFieldLabels()
    {
        return $this->fieldLabel;
    }

    public function getSelectChoicesCalculations()
    {
        return $this->selectChoicesCalculations;
    }

    public function getFieldOptions()
    {
        return $this->choiceLabels;
    }

    public function getFieldNotes()
    {
        return $this->fieldNote;
    }

    public function getFieldValidations()
    {
        return $this->fieldValidation;
    }

    public function getFieldMaximums()
    {
        return $this->fieldMax;
    }

    public function getFieldMinimums()
    {
        return $this->fieldMin;
    }

    public function getFieldCheckBoxes()
    {
        return $this->fieldCheckBox;
    }

// todo is it better precalculated and stored in memory or on demand?
    public function getFieldsInInstrument($instrumentName)
    {
        $fieldNames = [];
        $foundForm = false;
        foreach ($this->dataDictionary as $fieldName => $properties) {
            // echo $foundForm . $properties['form_name'] . " $fieldName <br>";
            if ($foundForm && $properties['form_name'] != $instrumentName) {
                break;
            }
            if ($properties['form_name'] === $instrumentName) {
                $foundForm = true;
                $fieldNames[] = $fieldName;
            }
        }
        return $fieldNames;
    }

    public function getFieldsByInstrument($instrumentName)
    {
        return $this->fieldsByInstrument[$instrumentName];
    }

    public function getFieldsByAllInstruments()
    {
        return $this->fieldsByInstrument;
    }

    public function getIdentifiers()
    {
        return $this->identifier;
    }

    public function getBranchingLogicFields()
    {
        return $this->branchingLogic;
    }

    public function getRequiredFields()
    {
        return $this->requiredField;
    }

    public function getCustomAlignment()
    {
        return $this->customAlignment;
    }

    public function getQuestionNumber()
    {
        return $this->questionNumber;
    }

    public function getMatrixGroupName()
    {
        return $this->matrixGroupName;
    }

    public function getFieldsInMatrix($matrixName)
    {
        return $this->matrixGroup[$matrixName];
    }

    public function getFieldAnnotations()
    {
        return $this->fieldAnnotation;
    }

    public function getEventsForInstrument($instrumentName)
    {
        $events = [];
        foreach ($this->eventGrid as $eventId => $instrumentSettings) {
            if (intval($instrumentSettings[$instrumentName]) === 1) {
                $events[] = $eventId;
            }
        }
        return $events;
    }

    /**
     * @param $needle   String    Needle to search for.
     * @param $category   string  category to search
     * @return array
     */
    public function searchFieldMeta(string $needle, string $category)
    {
        $result = null;
        $meta = [];
        switch ($category) {
            case 'instrument':
                $meta = $this->fieldsByInstrument;
                break;
            case 'section_header':
                $meta = $this->sectionHeader;
                break;
            case 'field_type':
                $meta = $this->fieldType;
                break;

            case 'field_label':
                $meta = $this->fieldLabel;
                break;

            case 'select_choices_or_calculations':
                $meta = $this->selectChoicesCalculations;
                break;

            case 'field_note':
                $meta = $this->fieldNote;
                break;
            case 'text_validation_type_or_show_slider_number':
                $meta = $this->fieldValidation;
                break;

            case 'text_validation_max':
                $meta = $this->fieldMax;
                break;

            case 'text_validation_min':
                $meta = $this->fieldMin;
                break;

            case 'identifier':
                $meta = $this->identifier;
                break;

            case 'branching_logic':
                $meta = $this->branchingLogic;
                break;

            case 'required_field':
                $meta = $this->requiredField;
                break;

            case 'custom_alignment':
                $meta = $this->customAlignment;
                break;

            case 'question_number':
                $meta = $this->questionNumber;
                break;

            case 'matrix_group_name':
                $meta = $this->matrixGroupName;
                break;

            case 'field_annotation':
                $meta = $this->fieldAnnotation;
                break;
        }

        foreach ($meta as $fieldName => $value) {
            if (strpos($value, $needle) !== false) {
                $result[$fieldName] = $value;
            }
        }
        return $result;
    }


    /**
     * @return null|string[]
     */
    public function getSectionHeaders()
    {
        if (isset($this->sectionHeader) && !is_null($this->sectionHeader)) {
            return $this->sectionHeader;
        } else {
            return null;
        }
    }

    public function getDataDictionary()
    {
        return $this->dataDictionary;
    }

    public function getFieldByValidationFormat($format)
    {
        $fields = [];
        foreach ($this->fieldValidation as $fieldName => $validation) {
            if ($validation === $format) {
                $fields[] = $fieldName;
            }
        }
        return $fields;
    }

    public function getChoiceLabels($fieldName)
    {
        return $this->choiceLabels[$fieldName];
    }
}