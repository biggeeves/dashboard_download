<?php

namespace DCC\RecordStatusDownload;

require_once dirname(__FILE__) . DS . 'DictionaryHelper.php';

// require_once "emLoggerTrait.php";

use DCC\RecordStatusDownload\DictionaryHelper;
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
    protected $pid;
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
     * @var bool|null
     */
    private $hasData;
    /**
     * @var string|null
     */
    private $nonSelected;
    /**
     * @var string|null
     */
    private $selected;
    /**
     * @var \DCC\RecordStatusDownload\DictionaryHelper|null
     */
    private $dictionary;

    /**
     * @param int $project_id Global Variable set by REDCap for Project ID.
     */
    public function redcap_project_home_page(int $project_id)
    {

    }

//    // check user permissions  By default system administrators and people with design rights will automatically
//    // see the link
//    public function redcap_module_link_check_display($project_id, $link)
//    {
//        // todo what rights should a user have to the display and the download
//        // If user
//        // return true;
//    }


    /**
     * Initialize class variables.
     */
    function __construct()
    {
        parent::__construct();
    }

    public function createDictionary()
    {
        $this->dictionary = new DictionaryHelper();
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

        $this->setCanDownload(); // should return true or false

        $this->transformData();

    }

    public function initialize()
    {
        $this->pid = $this->getProjectId();

        if (!$this->pid) {
            return;
        }

        $this->createDictionary();

        $this->setReturnJson();

        if (intval($_GET['dataLimit']) <= 50 && isset($_GET['dataLimit'])) {
            $this->dataLimit = intval($_GET['dataLimit']);
        } else {
            $this->dataLimit = 5;
        }
        $this->nonSelected = 'btn-info';
        $this->selected = 'btn-warning';

        $this->setGridType();

        $this->setUserInfo();

        $this->instrumentNames = REDCap::getInstrumentNames();

        $x = $this->dictionary->getInstrumentNames();
// todo keep simplifying this by using the Dictionary Class.
        $this->limitUserToInstruments();

        $this->setEventNames();

        $this->setSpecificId();

        $this->setEventNameLabels();

        $this->setInstrumentCompleteVar($this->instrumentNames);

        $this->setEventGrid();

        // Get the project's Record ID field
        $this->record_id_field = REDCap::getRecordIdField();
        $this->completedFields[] = $this->record_id_field;

        $this->data = REDCap::getData('array', $this->specificId, $this->completedFields, null, $this->group_id);

        if (empty($this->data)) {
            $this->hasData = false;
        } else {
            $this->hasData = true;
        }

    }

    private function setCanDownload()
    {
        $this->canDownload = false;
        $this->rights = REDCap::getUserRights($this->userid);
        if ($this->rights[$this->userid]["data_export_tool"] === "1") {
            $this->canDownload = true;
        }
        return $this->canDownload;
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
        $user = USERID;
        // $user = 'gneils';  // todo for testing
        $this->rights = REDCap::getUserRights($user);
        $this->group_id = $this->rights[$user]['group_id'];
        $this->userRights = array_shift($this->rights);
    }

    public function transformData()
    {
        if ($this->gridType === 'columns') {
            $this->transformToColumns();
        } elseif ($this->gridType === 'simplified') {
            $this->transformToSimplified();
        } else {
            $this->transformToRows();
        }
    }


    /**TODO the JSON returned for repeat instances has the word "repeat_instance" in place of the event key.
     * The code will have to look for repeat_instance:
     * Within repeat_instance is 1) event_id 2) instrument_name 3) ArrayID => variables.
     */
    /**
     * @return string
     */
    private function transformToSimplified()
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
            // Limit number of sample rows shown for HTML.  Skip if JSON is returned.
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
    private function transformToRows()
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
                        if (!is_null($inEvent)) {
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
    private function transformToColumns()
    {
        // todo handle infinitely repeating events

        // because the variable name must be unique, the event ID is appended to the instrument name
        $this->json = [];

        $this->htmlTable = '<table class="table table-hover table-striped table-bordered">';

        $headerEvents = '<tr><th></th>';
        $headerInstruments = '<tr><th>' . $this->record_id_field . '</th>';
        $this->json[0][] = $this->record_id_field;
        foreach ($this->eventGrid as $eventId => $instruments) {
            $colspan = 0;
            $headerEvents .= "<th colspan='";
            $hasAtLeastOneForm = false;
            foreach ($instruments as $instrumentName => $inEvent) {
                if (intval($inEvent) === 1) {
                    $hasAtLeastOneForm = true;
                    $colspan++;
                    // debug
                    $headerInstruments .= '<th title="' . $this->instrumentNames[$instrumentName] . '">' . $instrumentName . '</th>' . PHP_EOL;
                    $this->json[0][] = $instrumentName . '_' . $eventId;
                }
            }
            if (!$hasAtLeastOneForm) {
                $headerInstruments .= '<th>NA</th>' . PHP_EOL;
                $this->json[0][] = '';
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
                $hasAtLeastOneForm = false;
                if (array_key_exists($eventId, $this->data[$id])) {

                    // the data has the eventID.  Cycle through all instruments and show completed var status.
                    foreach ($instruments as $instrumentName => $inEvent) {

                        echo '<script>console.log("' . $eventId . ':' . $instrumentName . ':' . $inEvent . '")</script>';
                        if ($inEvent) {
                            $hasAtLeastOneForm = true;
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
                } else {
                    foreach ($instruments as $instrumentName => $inEvent) {
                        if (!$inEvent) {
                            continue;
                        } else {
                            $hasAtLeastOneForm = true;
                            $this->htmlTable .= "<td>-</td>" . PHP_EOL;
                            $this->json[$row][] = '';
                        }
                    }
                }
                if (!$hasAtLeastOneForm) {
                    $this->json[$row][] = '';
                    $this->htmlTable .= '<td>.</td>';
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
    public function renderArray($output, $title = '')
    {
        echo '<hr><h4>' . $title . '</h4><pre>';
        print_r($output);
        echo '</pre>';
    }

    private function renderLegend()
    {
        return '<div class="row"><div class="col-1">Legend</div>' .
            '<div class="col-11">Blank = Empty ' .
            '| <strong>0</strong> = Incomplete or Empty' .
            '| <strong>1</strong> = Unverified' .
            '| <strong>2</strong> = Complete</div></div>';
    }

    /**
     * @return string
     */
    private function renderButtons()
    {
        $linkOptions = $this->buildLinkOptions();

        if (!REDCap::isLongitudinal()) {
            $html = '';
        } else {
            $html = 'Display events in...';
            $columnClass = $this->nonSelected;
            $rowClass = $this->nonSelected;
            $simplifiedClass = $this->nonSelected;
            if ($this->gridType === 'columns') {
                $columnClass = $this->selected;
            } else if ($this->gridType === 'simplified') {
                $simplifiedClass = $this->selected;
            } else {
                $rowClass = $this->selected;
            }
            $html .= '<a class="btn ' . $rowClass . ' pr-4 mx-3" href="'
                . $this->getUrl('index.php') . '&type=rows' . $linkOptions . '">Rows</a>' . ' or ' .
                '<a class="btn ' . $simplifiedClass . ' pr-4 mx-3" ' .
                'href="' . $this->getUrl('index.php') . '&type=simplified' . $linkOptions . '">Rows Simplified</a>' .
                ' or ' .
                '<a class="btn ' . $columnClass . ' pr-4 mx-3" ' .
                'href="' . $this->getUrl('index.php') . '&type=columns' . $linkOptions . '">Columns</a>';
        }

        $html = '<h4>' . $html . '|' . $this->renderDownloadButton() . $this->renderMessageArea() . "</h4>";

        return $html;
    }

    private function renderMessageArea()
    {
        return '<span class="pl-4" id="dbr_message"></span>';
    }

    private function renderDownloadButton()
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

        $html = '<button class="btn ' . $this->selected . ' btn-sm" ' .
            'onclick="getJSON(' . $urlToGet . ', ' . $fileName . ')"' .
            '>Download</button>';

        return $html;
    }

    private function renderOptions()
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

        if (isset($_GET['showJson']) && $_GET['showJson'] === 'y') {
            $this->renderArray($this->json, 'JSON');
        }

        if (isset($_GET['userRights']) && $_GET['userRights'] === 'y') {
            $this->renderArray($this->userRights, 'userRights');
        }
        if (isset($_GET['dag']) && $_GET['dag'] === 'y') {
            $this->renderArray($this->group_id, 'Group ID/DAG');
        }

    }

    public function renderPage()
    {

        echo '<h2 class="">Record Status Dashboard</h2>';
        echo '<p class="mb-5">The record status report here is slightly different compared to REDCap\'s.' .
            ' The status is simplified in this report. Incomplete or Empty can not be differentiated' .
            ' as an instrument can be empty, but due to another instrument in the same event having data,' .
            ' the value will be incomplete for all instruments in that event, even empty ones.' .
            ' Unverified and Incomplete are the same as in REDCap.</p>';

        if (!$this->hasData) {
            echo '<p>There is no data to display.</p>';
            return;
        }
        $this->renderOptions();
        echo $this->renderButtons();
        if ($this->gridType === 'columns') {
            echo '<p>Columns: Columns are grouped by events. Instruments are .' .
                ' Each event is a group of columns.</p>' .
                '<p>Sample:</p>';
            echo '<div class="table-responsive">';
            echo $this->htmlTable;
            echo '</div>';
            echo $this->renderLegend();
        } elseif ($this->gridType === 'simplified') {
            echo '<p>Simplified: Each cell contains the total number of instruments a record has across all events.</p>' .
                '<p>Sample:</p>';
            echo '<div class="table-responsive">';
            $this->jsonToHTMLTable();
            echo '</div>';
        } else {
            echo '<p>Rows: Every event is in it\'s own row.</p><p>Sample:</p>';
            echo '<div class="table-responsive">';
            $this->jsonToHTMLTable();
            echo '</div>';
            echo $this->renderLegend();
        }
        echo $this->getCSS();
        echo $this->renderScripts();
    }

    public function hasPid()
    {
        if (is_null($this->pid)) {
            return false;
        }
        return true;
    }

    private function limitUserToInstruments()
    {
        foreach ($this->userRights['forms'] as $instrumentName => $right) {
            if (intval($right) === 0) {
                unset($this->instrumentNames[$instrumentName]);
            }
        }
    }

    private function buildLinkOptions()
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

        if (isset($_GET['showJson']) && $_GET['showJson'] === 'y') {
            $linkOptions .= '&showJson=y';
        }

        if (isset($_GET['dataLimit']) && intval($_GET['dataLimit']) <= 50) {
            $linkOptions .= '&dataLimit=' . intval($_GET['dataLimit']);
        }

        if (isset($_GET['userRights']) && $_GET['userRights'] === 'y') {
            $linkOptions .= '&userRights=y';
        }
        if (isset($_GET['dag']) && $_GET['dag'] === 'y') {
            $linkOptions .= '&dag=y';
        }

        return $linkOptions;
    }
}