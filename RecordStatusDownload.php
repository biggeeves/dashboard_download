<?php

namespace DCC\RecordStatusDownload;
// require_once "emLoggerTrait.php";

use Exception;
use ExternalModules\AbstractExternalModule;
use REDCap;

/** Ideas
 * Request for developers: Make an external module for looking up the history of fields.
 * When submitting an advanced search remember what the search text was and pre-fill the search field with it.
 **/

/**
 * SPECIAL NOTICE ABOUT REPEATING INSTRUMENTS/EVENTS if return_format = 'array':
 * Please note that if the project has repeating instruments or repeating events enabled
 * *and* is outputting data for at least one repeating instrument/event in 'array' return_format,
 * then the data for all repeating instruments/events will be returned in a slightly different structure
 * in the array returned, in which the 2nd-level array key will not be the event_id
 * but instead will be 'repeat_instances' (this exact text string).
 *
 * Then under this, event_id will be the 3rd-level array key,
 * redcap_repeat_instrument will be the 4th-level array key,
 * redcap_repeat_instance will be the 5th-level array key,
 * the field variable name will be the 6th-level array key,
 * and the data values as the array values for each field.
 *
 * Note that non-repeating data will still be returned in the normal format in the array,
 * but any repeating data will be added to that array in this different format as described above
 * (thus you may have both formats represented in the array).
 *
 * Keep in mind that redcap_repeat_instance will always be numerical,
 * and redcap_repeat_instrument will be the unique form name of the instrument for a repeating instrument.
 * However, for repeating events specifically (as opposed to repeating instruments),
 * redcap_repeat_instrument will have a blank value.
 */

/**
 * Class RecordStatusDownload
 * @package DCC\RecordStatusDownload
 */
class RecordStatusDownload extends AbstractExternalModule
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
    private $eventLabels;
    /**
     * @var array|null
     */
    private $completedFields;

    private $completedValues = [
        0 => '',
        1 => 'U',
        2 => 'C'
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
     * @var mixed|null
     */
    private $data;
    /**
     * @var int|null
     */
    private $dataLimit;
    /**
     * @var |null
     */
    private $record_id_field;
    /**
     * @var array|null
     */
    public $json;
    /**
     * @var string|null
     */
    private $gridType;
    /**
     * @var |null
     */
    private $specificId;
    /**
     * @var |null
     */
    private $group_id;
    /**
     * @var false|null
     */
    public $returnJson;

    /**
     * @var String $pid Project id of current REDCap project.
     */
    private $pid;
    /**
     * @var string|null
     */
    private $htmlTable;
    /**
     * @var mixed|null
     */
    private $userRights;
    /**
     * @var |null
     */
    private $userid;
    /**
     * @var mixed|null
     */
    private $rights;


    /**
     * @param int $project_id Global Variable set by REDCap for Project ID.
     */
    public function redcap_project_home_page(int $project_id)
    {

    }

    /**
     * Initialize class variables.
     */
    function __construct()
    {
        parent::__construct();
        $this->initialize();
    }

    /**
     *  Main action for Dashboard Report
     *  1) Create report
     *  2) Allow users to download report.
     */
    public function controller()
    {

        global $project_id;
        if (!isset($project_id) || is_null($project_id)) {
            echo '<h2 class="alert alert-info" style="border-color: #bee5eb !important;">Please select a project</h2>';
            return;
        }

        // todo add user check to see if in project
        // todo add check if User Has Download Data Permission.
        $this->setCanDownload(); // should return true or false

        $this->transformData();

    }

    public function initialize()
    {
        $this->pid = $this->getProjectId();

        if (!$this->pid) {
            return;
        }

        $this->setReturnJson();

        $this->dataLimit = 5;

        $this->setGridType();

        $this->setUserInfo();

        $this->instrumentNames = REDCap::getInstrumentNames();
        $this->setEventNames();

        $this->setSpecificId();

        $this->setEventNameLabels();

        $this->setInstrumentCompleteVar($this->instrumentNames);

        $this->setEventGrid();

        // Get the project's Record ID field
        $this->record_id_field = REDCap::getRecordIdField();
        $this->completedFields[] = $this->record_id_field;

        $this->data = REDCap::getData('array', $this->specificId, $this->completedFields);

    }

    private function setCanDownload()
    {
        $canDownload = false;
        $rights = REDCap::getUserRights($this->userid);
        if ($rights[$this->userid]["data_export_tool"] === "1") {
            $canDownload = true;
            // exit("<div class='red'>You don't have permission to view this page</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
        return $canDownload;
    }

    private function setReturnJson()
    {
        if (isset($_GET['json']) && $_GET['json'] === 'y') {
            $this->returnJson = true;
        } else {
            $this->returnJson = false;
        }
    }

    private function setGridType()
    {
        $this->gridType = 'rows';
        if (isset($_GET['type'])) {
            if ($_GET['type'] === 'columns') {
                $this->gridType = $_GET['type'];
            } elseif ($_GET['type'] === 'simplified') {
                $this->gridType = $_GET['type'];
            }
        }
    }

    private function setSpecificId()
    {
        if (isset($_GET['id'])) {
            $this->specificId = $_GET['id'];
        } else {
            $this->specificId = null;
        }
    }

    private function setUserInfo()
    {
        $this->rights = REDCap::getUserRights(USERID);
        $this->group_id = $this->rights[USERID]['group_id'];
        $this->userRights = array_shift($this->rights);
    }

    public function transformData()
    {
        if ($this->gridType === 'columns') {
            $this->transformGridColumns();
        } elseif ($this->gridType === 'simplified') {
            $this->transformGridSimplified();
        } else {
            $this->transformEventsInRows();
        }
        if (empty($this->data)) {
            // todo Correct this to set the $this->json value
            echo '<h2>There is no data.</h2>';
        }
    }


    /**TODO the JSON returned for repeat instances has the word "repeat_instance" in place of the event key.
     * The code will have to look for repeat_instance:
     * Within repeat_instance is 1) event_id 2) instrument_name 3) ArrayID => variables.
     */
    /**
     * @return string
     */
    private function transformGridSimplified()
    {
        $this->json = [];
        $hasFormSum = []; // Array  Instrument => running count
        $row = 0;
        // todo handle infinitely repeating events
        $this->json[0][] = $this->record_id_field;

        // Create Table Column Headers
        foreach ($this->instrumentNames as $shortName => $longName) {
            $this->json[0][] = $longName;
        }
        $limitCounter = 0;
        foreach ($this->data as $recordId => $recordData) {
            $row++;
            // Limit number of sample rows shown
            if (!$this->returnJson) {
                $limitCounter++;
                if ($limitCounter > $this->dataLimit) {
                    break;  // Break here so all events for a record ID are shown instead of stopping part way through.
                }
            }

            // Set Counters to Zero
            foreach ($this->instrumentNames as $shortName => $longName) {
                $hasFormSum[$shortName] = 0;
            }
            $this->json[$row][] = $recordId;
            foreach ($recordData as $eventId => $fields) {
                foreach ($fields as $instrument => $completed) {
                    $form = substr($instrument, 0, strlen($instrument) - 9);
                    if ($instrument === $this->record_id_field) {
                        // skip the ID field.
                        continue;
                    }
                    if ($completed >= 1) {
                        $hasFormSum[$form] = $hasFormSum[$form] + 1;
                    }
                }
            }
            foreach ($this->instrumentNames as $ShortName => $longName) {
                $this->json[$row][] = $hasFormSum[$ShortName];
            }
        }
        return true;
    }


    private function jsonToHTMLTable()
    {
        $output = '<table class="table table-bordered table-striped">';
        for ($i = 0; $i < count($this->json); $i++) {
            $output .= '<tr>';
            $colTypeOpen = '<td>';
            $colTypeClose = '</td>';
            if ($i === 0) {
                $colTypeOpen = '<th>';
                $colTypeClose = '</th>';
            }
            foreach ($this->json[$i] as $value) {
                $output .= $colTypeOpen . $value . $colTypeClose;
            }
            $output .= '</tr>';
        }
        $output .= '</table>';
        echo $output;
    }

    /**
     * @return string
     */
    private function transformEventsInRows()
    {
        // todo handle infinitely repeating events
        $this->json = [];

        $this->json[0] = [$this->record_id_field, 'Event'];
        foreach ($this->instrumentNames as $instrumentName) {
            array_push($this->json[0], $instrumentName);
        }
        $limitCounter = 0;
        $row = 0;
        foreach ($this->data as $recordId) {
            if (!$this->returnJson) {
                if ($limitCounter > $this->dataLimit) {
                    break;  // Break here so all events for a record ID are shown instead of stopping part way through.
                }
            }
            foreach ($recordId as $eventId => $fields) {
                $limitCounter++;
                $row++;
                foreach ($fields as $instrument => $inEvent) {
                    if ($instrument === $this->record_id_field) {
                        $this->json[$row][] = $inEvent;
                        $this->json[$row][] = $this->eventLabels[$eventId];
                    } else {
                        if (intval($inEvent) != 0) {
                            $this->json[$row][] = $this->completedValues[$inEvent];
                        } else {
                            $this->json[$row][] = '';
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @return string
     */
    private function transformGridColumns()
    {
        // todo handle infinitely repeating events

        // because the variable name must be unique, the event ID is appended to the instrument name
        $this->json = [];

        $this->htmlTable = '<h3>Each event is a group of columns.</h3>' .
            '<h5>Sample data.</h5>' .
            '<table class="table table-hover table-striped table-bordered">';

        $headerEvents = '<tr><th></th>';
        $headerInstruments = '<tr><th>' . $this->record_id_field . '</th>';
        $this->json[0][] = $this->record_id_field;
        foreach ($this->eventGrid as $eventId => $instruments) {
            $colspan = 0;
            $headerEvents .= "<th colspan='";
            foreach ($instruments as $instrumentName => $inEvent) {
                if (intval($inEvent) === 1) {
                    $colspan++;
                    $headerInstruments .= '<th>' . $instrumentName . '</th>' . PHP_EOL;
                    $this->json[0][] = $instrumentName . '_' . $eventId;
                }
            }
            $headerEvents .= $colspan . "'>" . $this->eventLabels[$eventId] . "</th>";
        }
        $headerEvents .= "</tr>";
        $headerInstruments .= "</tr>";
        $this->htmlTable .= $headerEvents . $headerInstruments;

        // Create both the array and the display table.
        $row = 0;
        foreach ($this->data as $id => $event) {
            $row++;
            if (!$this->returnJson) {
                if ($row > $this->dataLimit) {
                    break;  // limit display table
                }
            }
            $this->htmlTable .= '<tr><td>' . $id . '</td>';
            $this->json[$row][] = $id;
            foreach ($this->eventGrid as $eventId => $instruments) {
                if (!array_key_exists($eventId, $this->data[$id])) {
                    foreach ($instruments as $instrumentName => $inEvent) {
                        if (!$inEvent) {
                            continue;
                        } else {
                            $this->htmlTable .= "<td></td>" . PHP_EOL;
                            $this->json[$row][] = $eventId;
                        }
                    }
                } else {
                    // the data has the eventID.  Cycle through all instruments and show completed var status.
                    foreach ($instruments as $instrumentName => $inEvent) {
                        if ($inEvent) {
                            $completedVarName = $instrumentName . '_complete';
                            $this->htmlTable .= '<td>';
                            $status = 0;
                            if ($event[$eventId][$completedVarName] != 0) {
                                $status = $event[$eventId][$completedVarName];
                                $this->htmlTable .= $this->completedValues[$status];
                            }
                            $this->json[$row][] = $status;
                            $this->htmlTable .= '</td>' . PHP_EOL;
                        }
                    }
                }
            }
            $this->htmlTable .= '</tr>' . PHP_EOL;
        }
        $this->htmlTable .= "</table>";
        return true;
    }

    /**
     * The URL to the JavaScript that powers the HTML search form.
     *
     * @return string URL to search.js
     */
    private function getJSUrl()
    {
        return $this->getUrl("js/controller.js");
    }

    /**
     * @return string
     */
    private function getCSSUrl()
    {
        return $this->getUrl("css/base.css");
    }

    /**
     * @return string
     */
    private function getCSS()
    {
        return '<link  rel="stylesheet" type="text/css" src="' . $this->getCSSUrl() . '"/>';
    }


    /**
     * Creates all of the scripts are necessary for Dictionary Search
     * @return string  settings, arrays, initializations, etc.  All scripts should be loaded here.
     */
    private function renderScripts()
    {
        return '<script src="' . $this->getJSUrl() . '"></script>';

    }

    /**
     * Creates a list of autogenerated REDCap variables for each instrument Completed status.
     * @param $instrumentNames array of instrument names.  Short Name => Long Name.
     *
     */
    private function setInstrumentCompleteVar(array $instrumentNames)
    {
        $this->completedInstrumentVars = [];
        $this->completedFields = [];
        foreach ($instrumentNames as $shortName => $longName) {
            $this->completedInstrumentVars[$shortName] = $shortName . '_complete';
            $this->completedFields[] = $shortName . '_complete';
        }
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
        $this->eventLabels = REDCap::getEventNames(false, false);
    }

    /**
     * Create a 2D array of events and if the instrument is in that event.
     * @return null if not longitudinal
     */
    private function setEventGrid()
    {
        global $project_id;
        // Check if project is longitudinal first
        if (!REDCap::isLongitudinal()) {
            return null;
        }

        $this->eventGrid = [];
        foreach ($this->eventNames as $eventId => $eventName) {
            try {
                $allFieldsByEvent = REDCap::getValidFieldsByEvents($project_id, $eventId);
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
     * Print_R any array/variable passed to it.  Optional, Add a title
     * @param $output
     * @param $title
     */
    private function renderArray($output, $title = '')
    {
        echo '<hr><h4>' . $title . '</h4><pre>';
        print_r($output);
        echo '</pre>';
    }

    private function renderLegend()
    {
        return '<ul><li>Blank = Not Done or Incomplete</li><li>U = Unverified</li><li>C = Complete</li></ul>';
    }

    /**
     * @return string
     */
    private function renderButtons()
    {
        $linkOptions = '';

        if (isset($_GET['instrumentNames']) && $_GET['instrumentNames'] === 'y') {
            $linkOptions .= '&instrumentNames=y';
        }

        if (isset($_GET['eventNames']) && $_GET['eventNames'] === 'y') {
            $linkOptions .= '&eventNames=y';
        }

        if (isset($_GET['eventLabels']) && $_GET['eventLabels'] === 'y') {
            $linkOptions .= '&eventLabels=y';
        }

        if (isset($_GET['completed']) && $_GET['completed'] === 'y') {
            $linkOptions .= '&completed=y';
        }

        if (isset($_GET['eventGrid']) && $_GET['eventGrid'] === 'y') {
            $linkOptions .= '&eventGrid=y';
        }

        if (isset($_GET['data']) && $_GET['data'] === 'y') {
            $linkOptions .= '&data=y';
        }

        if (isset($_GET['showjson']) && $_GET['showjson'] === 'y') {
            $linkOptions .= '&showjson=y';
        }


        if (!REDCap::isLongitudinal()) {
            $html = '';
        } else {
            $html = '<h4>Display events in...';
            $columnClass = 'btn-info';
            $rowClass = 'btn-info';
            $simplifiedClass = 'btn-info';
            if ($this->gridType === 'columns') {
                $columnClass = 'btn-warning';
            } else if ($this->gridType === 'simplified') {
                $simplifiedClass = 'btn-warning';
            } else {
                $rowClass = 'btn-warning';
            }
            $html .= '<a class="btn ' . $rowClass . ' pr-4 mx-3" href="'
                . $this->getUrl('index.php') . '&type=rows' . $linkOptions . '">Rows</a>' . ' or ' .
                '<a class="btn ' . $simplifiedClass . ' pr-4 mx-3" href="' . $this->getUrl('index.php') . '&type=simplified' . $linkOptions . '">Rows Simplified</a>' .
                ' or ' .
                '<a class="btn ' . $columnClass . ' pr-4 mx-3" href="' . $this->getUrl('index.php') . '&type=columns' . $linkOptions . '">Columns</a>' .
                '</h4>';
        }

        return $html;
    }

    private
    function renderMessageArea()
    {
        return '<p id="dbr_message"></p>';
    }

    private
    function renderDownloadButton()
    {
        global $project_id;
        $urlToGet = "'" . $this->getUrl("index.php") . "&json=y";

        $fileName = "'RecordStatusData_" .
            $project_id .
            '_' . date("Ymj_g_i") .
            ".csv'";

        if ($this->gridType === 'columns') {
            $urlToGet .= "&type=columns";
        } elseif ($this->gridType === 'simplified') {
            $urlToGet .= "&type=simplified";
        } else {
            $urlToGet .= "&type=rows&json=y";
        }
        $urlToGet .= "'";

        $html = '<p>' .
            '<button class="btn btn-success btn-sm" ' .
            'onclick="getJSON(' . $urlToGet . ', ' . $fileName . ')"' .
            '>Download</button>';

        return $html;
    }

    private
    function renderOptions()
    {
        if (isset($_GET['instrumentNames']) && $_GET['instrumentNames'] === 'y') {
            $this->renderArray($this->instrumentNames, 'Instrument Names');
        }

        if (isset($_GET['eventNames']) && $_GET['eventNames'] === 'y') {
            $this->renderArray($this->eventNames, 'Event Names');
        }

        if (isset($_GET['eventLabels']) && $_GET['eventLabels'] === 'y') {
            $this->renderArray($this->eventLabels, 'Event Labels');
        }

        if (isset($_GET['completed']) && $_GET['completed'] === 'y') {
            $this->renderArray($this->completedFields, 'Completed Fields');
        }

        if (isset($_GET['eventGrid']) && $_GET['eventGrid'] === 'y') {
            $this->renderArray($this->eventGrid, 'Event Grid');
        }

        if (isset($_GET['data']) && $_GET['data'] === 'y') {
            $this->renderArray($this->data, 'REDCap Data');
        }

        if (isset($_GET['showjson']) && $_GET['showjson'] === 'y') {
            $this->renderArray($this->json, 'JSON');
        }
    }

    public
    function renderPage()
    {

        $this->renderOptions();
        echo $this->renderButtons();
        echo $this->renderDownloadButton();
        echo $this->renderMessageArea();
        if ($this->gridType === 'columns') {
            echo $this->renderLegend();
            echo $this->htmlTable;
        } elseif ($this->gridType === 'simplified') {
            $this->jsonToHTMLTable();
        } else {
            echo $this->renderLegend();
            $this->jsonToHTMLTable();
        }
        if (empty($this->data)) {
            echo '<h2>There is no data.</h2>';
        }
        echo $this->getCSS();
        echo $this->renderScripts();
    }

    public
    function hasPid()
    {
        if (is_null($this->pid)) {
            return false;
        }
        return true;
    }

}