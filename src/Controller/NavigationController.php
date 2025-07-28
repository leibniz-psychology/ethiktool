<?php

namespace App\Controller;

use App\Abstract\ControllerAbstract;
use App\Classes\CheckDocClass;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NavigationController extends ControllerAbstract
{
    use AppDataTrait; // for project title
    use ProjectdetailsTrait; // for route parameter

    /** @throws Exception if the document check fails */
    public function showNavigation(Request $request): Response {
        $windows = [self::appDataNodeName => '', self::contributorsNodeName => '', self::projectdetailsNodeName => ''];
        $session = $request->getSession();
        $hasDoc = $session->has(self::docName);
        $appNode = $hasDoc ? $this->getXMLfromSession($session) : '';
        if ($hasDoc) { // application is open
            // application data
            $windows[self::appDataNodeName] = [$this->setSubMenu(self::appDataNodeName,$request,cutName: false)];
            // contributors
            $windows[self::contributorsNodeName] = [self::label => 'app_contributors', self::error => CheckDocClass::getDocumentCheck($request,'contributors')];
            // project details
            $projectDetailsArray = $this->setSubMenu(self::projectdetailsNodeName,$request);
            foreach ($this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]) as $studyIndex => $study) {
                $studyArray = $this->setSubMenu(self::projectdetailsNodeName,$request,$studyIndex);
                foreach ($this->addZeroIndex($study[self::groupNode]) as $groupIndex => $group) {
                    $groupArray = $this->setSubMenu(self::projectdetailsNodeName,$request,$studyIndex,$groupIndex);
                    foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measureIndex => $measureTimePoint) {
                        $groupArray[$measureIndex][self::subPages] = $this->setSubMenu(self::projectdetailsNodeName,$request,$studyIndex,$groupIndex,$measureIndex);
                    }
                    $studyArray[$groupIndex][self::subPages] = $groupArray;
                }
                $projectDetailsArray[$studyIndex][self::subPages] = $studyArray;
            }
            $windows[self::projectdetailsNodeName] = [[self::label => $this->translateString('pages.projectdetails.title'), self::route => 'app_landing', self::subPages => $projectDetailsArray, self::error => CheckDocClass::getDocumentCheck($request,self::projectdetailsNodeName)]];
        }
        else {
            foreach ($windows as $page => $value) {
                $isNotContributors = $page!==self::contributorsNodeName;
                $windows[$page] = [[self::label => $this->translateString('pages.'.lcfirst($page).($isNotContributors ? '.title' : '.contributors')), self::route => '']];
            }
        }
        if (is_null($session->get(self::language))) {
            $session->set(self::language,\Locale::getDefault());
        }
        $activeRoute = $request->get('_route');
        $routeParams = $activeRoute!=='app_landing' ? $request->get('_route_params') : $session->get(self::landing);
        $activeLevels = [];
        foreach (array_diff_key($routeParams,['_locale' => '', 'page' => '']) as $level => $id) {
            $activeLevels[$level] = (int)($id);
        }
        if (array_key_exists(self::informationNode,$routeParams)) {
            $activeRoute = 'app_'.$routeParams[self::informationNode];
        }
        return $this->render('_navigationSidebar.html.twig',
            ['content' => [self::fileName => ['title' => $this->translateString('multiple.filename').':', 'titleValue' => $hasDoc ? $session->get(self::fileName) : ''],
                           self::projectTitle => ['title' => $this->translateString('coreData.projectTitle').':', 'titleValue' => $hasDoc ? ((string) $appNode->{self::appDataNodeName}->{self::coreDataNode}->{self::projectTitle}) : '']],
             'windows' => $windows,
             'activeRoute' => $activeRoute,
             'routeParams' => $activeLevels,
             'isComplete' => $this->getErrors($request,returnCheck: true),
             'anyError' => $this->checkAnyError($windows)]);
    }

    /** Checks if a key 'errors' exists in the given array or any sub-array.
     * @param array|string $windows array to be checked
     * @return bool true if an 'error' key exists and is true, false otherwise
     */
    private function checkAnyError(array|string $windows): bool {
        if (!is_array($windows)) {
            return false;
        }
        if (array_key_exists(self::error,$windows)) {
            return $windows[self::error] ?: (array_key_exists(self::subPages, $windows) && $this->checkAnyError($windows[self::subPages]));
        }
        else {
            foreach ($windows as $page) {
                if ($this->checkAnyError($page)) {
                    return true;
                }
            }
        }
        return false;
    }
}
