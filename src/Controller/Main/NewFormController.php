<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\NewFormType;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Main\CompleteFormTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use App\Traits\Contributors\ContributorsTrait;
use DOMException;
use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

class NewFormController extends ControllerAbstract
{
    use AppDataTrait; // application data
    use ContributorsTrait; // contributors
    use ProjectdetailsTrait; // project details
    use CompleteFormTrait; // complete form

    private const filenameTemp = 'filenameTemp'; // used to save the entered filename if the language was changed
    private const committeeTemp = 'committeeTemp'; // used to save the chosen committee if the language was changed

    #[Route('/newForm', name: 'app_newForm')]
    public function showNewForm(Request $request): Response {
        $session = $request->getSession();
        if ($session->get(self::docName)!==null) { // page was opened after a proposal was created/loaded, but not by the button in the sidebar
            return $this->redirectToRoute('app_main');
        }

        $general = $this->createFormAndHandleRequest(NewFormType::class,[self::fileName => $session->get(self::filenameTemp) ?? '', self::committee => $session->get(self::committeeTemp) ?? '', self::language => $session->get(self::language)],$request);
        if ($general->isSubmitted()){
            $response = $request->request->all();
            $data = $response['new_form'];
            $submitDummy = $data[self::submitDummy];
            if (array_key_exists('newFormSubmit',$response)) { // "save" was clicked
                try {
                    $data = $general->getData();
                    $committeeType = $data[self::committee];
                    $this->setCommittee($session,$committeeType,$session->get(self::language));
                    $isEUB = $committeeType===self::committeeEUB;
                    // create xml-document
                    $doc = $this->createDOM();

                    $doc->appendChild($doc->createElement('Application'));
                    $xml = new SimpleXMLElement($doc->saveXML());
                    $this->setToolVersion($xml);
                    $xml->addChild(self::committee, $committeeType);
                    $this->addChildNodes($xml,[self::saveNodeName,self::pdfNodeName]);
                    $appDataNode = $xml->addChild(self::appDataNodeName);

                    // sub-elements
                    // application data
                    // core data
                    $coreDataNode = $appDataNode->addChild(self::coreDataNode);
                    $coreDataNode->addChild(self::projectTitle);
                    $this->addChosenNode($coreDataNode,self::projectTitleParticipation);
                    $this->addChosenNode($coreDataNode,self::applicationType);
                    if ($isEUB) {
                        $coreDataNode->addChild(self::qualification);
                    }
                    $applicantNode = $coreDataNode->addChild(self::applicant);
                    foreach (self::applicantContributorsInfosTypes as $info) {
                        $applicantNode->addChild($info);
                    }
                    $coreDataNode->addChild(self::projectStart)->addChild(self::chosen);
                    $this->addChildNodes($coreDataNode,[self::projectEnd,self::funding]);
                    $this->addChosenNode($coreDataNode,self::conflictNode);
                    $coreDataNode->addChild(self::supportNode);
                    if ($isEUB) {
                        $coreDataNode->addChild(self::guidelinesNode);
                    }

                    // votes
                    $votesNode = $appDataNode->addChild(self::voteNode);
                    $this->addChosenNode($votesNode,self::otherVote);
                    $this->addChosenNode($votesNode,self::instVote);
                    // medicine
                    $medicineNode = $appDataNode->addChild(self::medicine);
                    $this->addChosenNode($medicineNode,self::medicine);
                    $this->addChosenNode($medicineNode,self::physicianNode);
                    // summary;
                    $appDataNode->addChild(self::summary)->addChild(self::descriptionNode);

                    // contributors
                    $this->addContributor($xml->addChild(self::contributorsNodeName),[self::infosNode => array_fill_keys(self::applicantContributorsInfosTypes,''), self::taskNode => [self::applicationNode => '']]);

                    // project details
                    $this->addMeasurement($xml->addChild(self::projectdetailsNodeName),self::studyNode,'');

                    // complete form
                    $this->addChildNodes($xml->addChild(self::completeFormNodeName),[self::consent,self::descriptionNode,self::bias,self::consentFurther]);

                    // save additional information in session
                    $session->set(self::fileName, $data[self::fileName]);
                    $session->set(self::contributorsSessionName, [[0 => $this->xmlToArray($xml->{self::contributorsNodeName}->{self::contributorNode})]]); // save contributors in session
                    $session->set(self::docName,[$xml->asXML()]); // save xml-document in session
                    $session->set(self::newForm,'');
                    return $this->redirectToRoute('app_main');
                } catch (DOMException | Exception $e) { // Exception is for SimpleXMLElement
                    return $this->setErrorAndRedirect($session);
                }
            }
            elseif (array_key_exists('backToMain',$response)) { // "abort" was clicked
                $this->resetTemp($session);
                return $this->redirectToRoute('app_main');
            }
            elseif (str_contains($submitDummy,self::language)) { // one of the language elements was clicked
                $newLanguage = substr(trim(explode("\n",$submitDummy)[1] ?? ''), strlen(self::language.':'));
                $session->set(self::filenameTemp, $data[self::fileName]);
                $session->set(self::committeeTemp,$data[self::committee]);
                $session->set(self::language, $newLanguage);
                return $this->redirectToRoute('app_newForm',['_locale' => $newLanguage]);
            }
            else { // "quit" was clicked or a proposal was loaded -> all other buttons that lead to other pages are disabled
                return $this->saveDocumentAndRedirect($request,$this->getXMLfromSession($request->getSession())); // save the same document again
            }
        } // if ($general->isSubmitted())
        return $this->render('Main/newForm.html.twig', $this->setRenderParameters($request,$general));
    }

    /** Resets the temp variables for saving filename and committee if the language was changed.
     * @param Session $session current session
     * @return void
     */
    private function resetTemp(Session $session): void {
        $session->set(self::filenameTemp,'');
        $session->set(self::committeeTemp,'');
    }
}