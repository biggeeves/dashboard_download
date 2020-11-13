<?php
/** @var RecordStatusDownload $module */
/** @var $project_id */

if (is_null($module) || !($module instanceof DCC\RecordStatusDownload\RecordStatusDownload)) {
    echo "Module Error";
    exit();
}
require_once dirname(__FILE__) . DS . 'DictionaryHelper.php';

error_reporting(0);
$module->initialize();
if (!$module->hasPid()) {
    echo('Project ID is required');
} else {
    $dictionary = new DCC\RecordStatusDownload\DictionaryHelper();
//    $dictionary33 = new DCC\RecordStatusDownload\DictionaryHelper(33);
//    $module->renderArray($dictionary->getInstrumentNames(), 'Instrument Names');
//    $module->renderArray($dictionary->getUserRights(), 'User Rights');
//    $module->renderArray($dictionary->getEventNames(), 'Event Names');
//    $module->renderArray($dictionary->getEventNameLabels(), 'Event Labels');
//    $module->renderArray($dictionary->getCompletedInstrumentVars(), 'CompletedInstruments');
//    $module->renderArray($dictionary->getEventGrid(), 'Event Grid');
//    $module->renderArray($dictionary->getFieldNames(), 'Field Names');
//    $module->renderArray($dictionary->getSectionHeaders(), 'Section Headers');
//    $module->renderArray($dictionary->getFieldTypes(), 'Field Types');
//    $module->renderArray($dictionary->getFieldLabels(), 'Field Labels');
//    $module->renderArray($dictionary->getSelectChoicesCalculations(), 'Select Choices Calculations');
//    $module->renderArray($dictionary->getFieldOptions(),'Field Options');
//    $module->renderArray($dictionary->getFieldNotes(),'Field Notes');
//    $module->renderArray($dictionary->getFieldValidations(), 'Field Validations');
//    $format = 'date_mdy';
//    $module->renderArray($dictionary->getFieldByValidationFormat('date_mdy'), 'Validations with ' . $format);
//    $module->renderArray($dictionary->getFieldMaximums(),'Field Maximums');
//    $module->renderArray($dictionary->getFieldMinimums(),'Field Minimums');
//    $module->renderArray($dictionary->getFieldCheckBoxes(),'Field CheckBoxes');
//       $module->renderArray($dictionary->getFieldProperties('fname_consent'),'Field Properties');
//       $module->renderArray($dictionary->getFieldsInInstrument('background_questionnaire'),'Fields In Instrument');
//       $module->renderArray($dictionary->getFieldsByAllInstruments(),'Fields By All Instruments');
//       $module->renderArray($dictionary->getFieldsByInstrument('background_questionnaire'),'Fields By Instrument');
//       $module->renderArray($dictionary->getIdentifiers(),'Identifier Fields');
//       $module->renderArray($dictionary->getBranchingLogicFields(),'Branching Logic Fields');
//       $module->renderArray($dictionary->getRequiredFields(),'Required Fields');
//       $module->renderArray($dictionary->getCustomAlignment(),'Custom Alignment Fields');
//       $module->renderArray($dictionary->getQuestionNumber(),'Question Number Fields');
//    $module->renderArray($dictionary->getMatrixGroupName(), 'Matrix Fields');
//    $matrixName = 'apb';
//    $module->renderArray($dictionary->getFieldsInMatrix($matrixName), 'Matrix ' . $matrixName . 'Fields');
//    $module->renderArray($dictionary->getFieldAnnotations(), 'Field with Annotations');
//    $instrumentName = 'lumbar_puncture_followup_phone_call';
//    $module->renderArray($dictionary->getEventsForInstrument($instrumentName), 'Events for '. $instrumentName );
//    //
//    $needle = 'Dynamic';
//    $category = 'field_label';
//    $module->renderArray($dictionary->searchFieldMeta($needle, $category), 'Search Field Meta Results');
    $fieldName = 'some_missing_not_defined';
    $module->renderArray($dictionary->getChoiceLabels($fieldName), 'Data Dictionary Array');
//       $module->renderArray($dictionary->getDataDictionary(),'Data Dictionary Array');
    echo "<br>Development" . __FILE__ . ":" . __LINE__ . "<br>";
    die;
    $module->controller();
    if ($module->returnJson) {
        REDCap::logEvent("Downloaded Record Status Dashboard");
        $module->transformData();
        header('Content-Type: application/json');
        echo json_encode($module->json);
        exit();
    } else {
        require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        $module->renderPage();
    }
}
