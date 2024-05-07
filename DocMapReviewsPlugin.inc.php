<?php
/**
 * @file plugins/generic/docMapReviews/DocMapReviewsPlugin.inc.php
 *
 * Copyright (c) --

 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @class DocMapReviewsPlugin
 * @ingroup plugins_generic_docMapReviews
 * @brief Plugin class for the DocMap Reviews plugin.
 */
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.submission.PKPSubmission');
import('plugins.generic.docMapReviews.DocMapReviewsSchemaMigration');

define('DOCMAPS_API_URL', 'https://sciety.org/docmaps/v1/articles/');
define('DOCMAPS_JSON_VERSION', '.docmap.json');

class DocMapReviewsPlugin extends GenericPlugin {
    /** @var array Lazy loaded review service list */
    private $_reviewServiceList = null;

    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            import('plugins.generic.docMapReviews.classes.DisplayReviewsPreference');
            import('plugins.generic.docMapReviews.classes.DisplayReviewsPreferenceDAO');

            $displayReviewsPreferenceDAO = new DisplayReviewsPreferenceDAO();
            DAORegistry::registerDAO('DisplayReviewsPreferenceDAO', $displayReviewsPreferenceDAO);

            HookRegistry::register('Template::Workflow::Publication', array($this, 'addToWorkflow'));
            HookRegistry::register('TemplateManager::display',array($this, 'addGridhandlerJs'));
            HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'submissionWizard'));
            HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler'));
            HookRegistry::register('Templates::Preprint::Details', array($this, 'callbackSharingDisplay'));
        }

        return $success;
    }

    /**
     * Provide a name for this plugin
     *
     * The name will appear in the plugins list where editors can
     * enable and disable plugins.
     */
    public function getDisplayName() {
        return 'DocMap Reviews';
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the plugins list where editors can
     * enable and disable plugins.
     */
    public function getDescription() {
        return 'This plugin allows reviews via DocMaps to be displayed on the pre-review detail pages.';
    }

    private function getAuthorId($user) {
        $orcid = $user->getOrcid();
        return ($orcid != "") ? $orcid : "mailto:{$user->getEmail()}";
    }

    public function getDoi($submission) {
        return $submission->getData('publications')[0]->getData('pub-id::doi');
    }

    public function getDoiById($id) {
        import('classes.submission.Submission');
        $submission = Services::get('submission')->get($id);
        $submission = $submission->getData('publications')[0]->getData('pub-id::doi');
        return $submission;
    }

    private function getSubmissionType() {
        $applicationName = substr(Application::getName(), 0, 3);

        if($applicationName == 'ops') {
            return 'preprint';
        }

        return 'article';
    }

    /**
     * Retrieves the list of review services from the plugin settings and caches it
     * @return array List of review services, where key is the home URL and value is the inbox URL
     */
    function getReviewServiceList() {
        if (
            $this->_reviewServiceList === null
            && !is_array($this->_reviewServiceList = $this->getSetting($this->getCurrentContextId(), 'reviewServiceList'))
        ) {
            $this->_reviewServiceList = [];
        }
        return $this->_reviewServiceList;
    }

    public function sendHttpPostRequest($url, $data) {
        $ch = curl_init();
        $jsonData = json_encode($data);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));

        $response = curl_exec($ch);
        $result = json_decode($response);

        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return $result;
    }

    public function getInstallMigration() {
        return new DocMapReviewsSchemaMigration();
    }

    /**
     * @see Plugin::getInstallSitePluginSettingsFile()
     */
    public function getInstallSitePluginSettingsFile() {
        return $this->getPluginPath() . '/settings.xml';
    }

    private function isSubmissionPublished($submission) {
        return $submission->getData('status') === STATUS_PUBLISHED;
    }

    function getDisplayReviewsPreferences($submissionId) {
        /* @var $displayReviewsPreferenceDAO DisplayReviewsPreferenceDAO */
        $displayReviewsPreferenceDAO = DAORegistry::getDAO('DisplayReviewsPreferenceDAO');
        $docMapReviewsPreferencesResult = $displayReviewsPreferenceDAO->getBySubmissionId($submissionId)->toArray();

        return array_map(function($preference){
            return $preference->getData('displayReviews');
        }, $docMapReviewsPreferencesResult);
    }

    public function addToWorkflow($hookName, $params) {
        $smarty =& $params[1];
        $output =& $params[2];
        $submission = $smarty->get_template_vars('submission');
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $smarty->assign(
            'userIsManager',
            $user->hasRole(Application::getWorkflowTypeRoles()[WORKFLOW_TYPE_EDITORIAL], $request->getContext()->getId())
        );

        $smarty->assign([
            'submissionType' => $this->getSubmissionType(),
            'reviewServiceList' => $this->getReviewServiceList(),
            'originHomeUrl' => $this->getSetting($this->getCurrentContextId(), 'originHomeUrl'),
            'originInboxUrl' => $this->getSetting($this->getCurrentContextId(), 'originInboxUrl'),
            'actorName' => $user->getFullName(),
            'authorId' => $this->getAuthorId($user),
            'isPublished' => $this->isSubmissionPublished($submission),
            'doi' => $this->getDoi($submission),
            'displayReviewsPreferences' => $this->getDisplayReviewsPreferences($submission->getData('id')),
        ]);

        $output .= sprintf(
            '<tab id="docMapReviews" label="%s">%s</tab>',
            __('plugins.generic.docMapReviews.displayName'),
            $smarty->fetch($this->getTemplateResource('docMapReviewPreferences.tpl'))
        );
    }

    /**
     * Show citations part on step 3 in submission wizard
     * @param string $hookname
     * @param array $args
     * @return void
     */
    public function submissionWizard($hookname, array $args) {
        $templateMgr = &$args[1];
        $request = $this->getRequest();
        $submissionId = $request->getUserVar('submissionId');

        $this->templateParameters['submissionId'] = $submissionId;

        if (!empty($publicationWorkDb) && $publicationWorkDb !== '[]')
            $this->templateParameters['workModel'] = $publicationWorkDb;

        $this->templateParameters['statusCodePublished'] = STATUS_PUBLISHED;

        $templateMgr->assign($this->templateParameters);

        $templateMgr->display($this->getTemplateResource("submission/form/submissionWizard.tpl"));
    }

    /**
     * Permit requests to the grid handler
     * @param $hookName string The name of the hook being invoked
     * @param $args array The parameters to the invoked hook
     */
    function setupGridHandler($hookName, $params) {
        $component =& $params[0];
        if ($component == 'plugins.generic.docMapReviews.controllers.grid.DocMapReviewsGridHandler') {
            import($component);
            DocMapReviewsGridHandler::setPlugin($this);
            return true;
        }
        return false;
    }

    /**
     * Add custom gridhandlerJS for backend
     */
    function addGridhandlerJs($hookName, $params) {
        $templateMgr = $params[0];
        $request = $this->getRequest();
        $gridHandlerJs = $this->getJavaScriptURL($request, false) . DIRECTORY_SEPARATOR . 'DocMapReviewsGridHandler.js';
        $templateMgr->addJavaScript(
            'DocMapReviewsGridHandlerJs',
            $gridHandlerJs,
            array('contexts' => 'backend')
        );
        return false;
    }

    /**
     * Get the JavaScript URL for this plugin.
     */
    function getJavaScriptURL() {
        return Application::get()->getRequest()->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'js';
    }

    function getReviewWebContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    function validateDocMapPayload($payload) {
        if ($payload == ["message" => "Invalid DOI requested"]) {
            return false;
        }
        return true;
    }

    function getDocMapReviewsPreference($submissionId) {
        $displayReviewsPreferenceDAO = DAORegistry::getDAO('DisplayReviewsPreferenceDAO');
        $docMapReviewsPreferencesResult = $displayReviewsPreferenceDAO->getBySubmissionId($submissionId)->toArray();

        if (empty($docMapReviewsPreferencesResult)) {
            // No preference set, default to true
            return true;
        } else {
            $pref = reset($docMapReviewsPreferencesResult);
            return $pref->getData('displayReviews');
        }

    }

    function fetchDocMapReviewsByGroup($doi) {
        $url= DOCMAPS_API_URL . $doi . DOCMAPS_JSON_VERSION;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);
        // close curl resource to free up system resources
        curl_close($ch);
        $data = json_decode($result, true);

        // TODO - Iterate over groups of reviews

        $reviewGroups = array();
        $groupId = 0;

        if ($this->validateDocMapPayload($data)) {
            foreach($data as $group){
                $reviewGroups[$groupId]['name'] = $group['publisher']['name'];
                $reviewGroups[$groupId]['logo'] = $group['publisher']['logo'];

                $actions = $group['steps'][$group['first-step']]['actions'];
                $i = 0;

                foreach($actions as $action){
                    $date = new DateTime($actions[$i]['outputs'][0]['published']);
                    $formattedDate = $date->format('d M Y');
                    $contentLink = '';

                    foreach($actions[$i]['outputs'] as $output){
                        foreach($output['content'] as $content){
                            if($content['type'] == 'web-content'){
                                $contentLink = $content['url'];
                            }
                        }
                    }

                    $reviewGroups[$groupId]['reviews'][$i]=array(
                        'id' =>sprintf("%d%d", $groupId, $i),
                        'name'=> $actions[$i]['participants'][0]['actor']['name'],
                        'published' => $formattedDate,
                        'outputType' => $actions[$i]['outputs'][0]['type'],
                        'link' => $actions[$i]['outputs'][0]['content'][0]['url'],
                        'webContent' => $this->getReviewWebContent($contentLink),
                    );

                    $i++;
                }

                $groupId++;
            }
        }

        return $reviewGroups;
    }

    function callbackSharingDisplay($hookName, $params) {
        $templateMgr = $params[1];
        $templateOutput =& $params[2];
        $request = Application::get()->getRequest();
        $idPreprint=$request->getRouter()->getHandler()->preprint->_data['id'];
        $idPreprint=((int) $idPreprint);

        $shouldDisplayReviews = $this->getDocMapReviewsPreference($idPreprint);

        if ($shouldDisplayReviews) {
//            $doi = $this->getDoiById($idPreprint);
            $doi = "10.21203/rs.3.rs-955726/v1";
            $reviewGroups = $this->fetchDocMapReviewsByGroup($doi);
            $templateMgr->assign(
                array(
                    'doi' => $doi,
                    'idPreprint' => $idPreprint,
                    'reviewGroups' => $reviewGroups,
                )
            );
            $templateOutput .= $templateMgr->fetch($this->getTemplateResource('docMapReviews.tpl'));
        }

        return false;
    }

}