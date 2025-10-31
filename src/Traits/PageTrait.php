<?php

namespace App\Traits;

use DateTime;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Contains all variables, functions and methods that are used by forms (*Type classes) and by controllers. Each Trait for a specific page uses this Trait. The Type and Controller classes in turn use the page-specific Trait. */
trait PageTrait
{
    protected const toolVersionAttr = 'toolVersion';
    protected const toolVersion = '1.3.7';
    public static TranslatorInterface $translator;
    /** @var string session key for the committee type */
    protected const committeeType = 'committeeType';
    /** @var string name of xml node for the committee */
    protected const committee = 'committee';
    /** @var string name of the variable for session, forms, and views containing all committee parameters for, e.g., translations */
    protected const committeeParams = 'committeeParams';
    /** @var string name of parameter indicating if current committee type is in beta status. */
    protected const isCommitteeBeta = 'isCommitteeBeta';
    protected const committeeTypes = ['newForm.committee.types.TUC' => 'TUC', 'newForm.committee.types.EUB' => 'EUB', 'newForm.committee.types.JGU' => 'JGU', 'newForm.committee.types.DLR' => 'DLR', 'newForm.committee.types.testCommittee' => 'testCommittee'];
    protected const committeeTypesBeta = ['JGU','DLR']; // committees that are currently in beta status. Values must equal the value of $committeeTypes
    // constant variables
    /** Node name for nodes which indicate the selection of a radio button. */
    protected const chosen = 'chosen'; // for nodes whose content indicates which answer was chosen, if any
    protected const nameNode = 'name';
    protected const descriptionNode = 'description'; // for nodes whose content is a description
    protected const descriptionCap = 'Description';
    protected const projectTitle = 'projectTitle';
    protected const language = 'language'; // value needs to be the same as in ControllerAbstract
    protected const choiceTextHint = 'multiple.choiceTextHint'; // dropdowns and above disabled text fields
    protected const dummyParams = 'dummy'; // dummy key for parameter that is passed to the options array of the Form Builder
    // node names
    protected const studyNode = 'study';
    protected const groupNode = 'group';
    protected const measureTimePointNode = 'measureTimePoint';
    protected const committeeEUB = 'EUB';
    protected const committeeTUC = 'TUC';
    protected const committeeDLR = 'DLR';
    protected const position = 'position';
    protected const positionsStudent = 'student'; // must equal one of the keys in $positions
    protected const positionsPhd = 'phd'; // must equal one of the keys in $positions
    protected const positionOther = 'positionOther'; // must equal one of the keys in $positions
    protected const positionsTypes = ['professorship' => 'multiple.position.professorship', 'scientific' => 'multiple.position.scientific', 'phd' => 'multiple.position.phd', 'student' => 'multiple.position.student', 'positionOther' => 'multiple.position.positionOther'];
    protected const applicantContributorsInfosTypes = ['name', 'institution', 'professorship', 'eMail', 'position', 'phone'];
    protected const institutionInfo = 'institution'; // must equal one value in $applicantContributorsInfosTypes
    // contributor
    protected const contributorNode = 'contributor';
    protected const infosNode = 'infos';
    protected const taskNode = 'tasks';
    // variables for adding hints that inputs on pages are going to be removed
    private const pageNames = 'pageNames'; // key holding the page names with inputs
    private const pageInputs = 'pageInputs'; // key holding the inputs that will be removed
    // session variables
    protected const pdfApplication = 'pdfApplication'; // rendered view for preview
    protected const pdfParticipation = 'pdfParticipation'; // rendered view for preview
    protected const pdfParticipationArray = 'pdfParticipationArray'; // array of array with three elements containing the IDs of the participation documents to be created
    protected const pdfParticipationCustom = 'pdfParticipationCustom'; // array with one sub-array for each study, group, and time point. Value for each time point is an array containing all values for which a custom pdf will be added

    // functions

    /** Gets the committee type.
     * @param Session $session current session
     * @return string committee type
     */
    protected function getCommitteeType(Session $session): string {
        return $session->get(self::committeeParams)[self::committeeType] ?? '';
    }

    /** Creates a string with concatenated IDs and underscores between them. Optionally the string can be prefixed or suffixed.
     * @param array $ids array of three elements in following order: study ID, group ID, measure time point ID
     * @param string $prefix string to be added at the beginning
     * @param string $suffix string to be added at the end
     * @return string concatenated IDs
     */
    protected function concatIDs(array $ids,string $prefix = '', string $suffix = ''): string {
        return $prefix.$ids[0].'_'.$ids[1].'_'.$ids[2].$suffix;
    }

    /** Appends 'Text' at the end of the string.
     * @param string $string string which gets appended
     * @return string $string with 'Text' appended
     */
    protected function appendText(string $string): string {
        return $string.'Text';
    }

    /** Adds a child to \$element with the name \$name. To this new node a child with the name 'chosen' is added.
     * @param SimpleXMLElement $element node where the child get added
     * @param string $name name of the new child
     * @return SimpleXMLElement the node with the name $name
     */
    protected function addChosenNode(SimpleXMLElement $element, string $name): SimpleXMLElement {
        $returnNode = $element->addChild($name);
        $returnNode->addChild(self::chosen);
        return $returnNode;
    }

    /** Converts a string representing a date to the format specific for the language.
     * @param string|DateTime $dateString string to be converted
     * @param bool $noTime if true, only the date will be added, without the current time
     * @return string converted string
     * @throws \DateMalformedStringException if an exception occurs during creation of the DateTime element
     */
    protected function convertDate(string|DateTime $dateString, bool $noTime = true): string {
        if ($dateString!=='') {
            if (is_string($dateString)) {
                $dateString = new DateTime($dateString);
            }
            $dateString = $dateString->format($this->translateString('dateFormat',['noTime' => $this->getStringFromBool($noTime)]));
        }
        return $dateString;
    }

    /** Returns the Berlin timezone
     * @return \DateTimeZone Berlin timezone
     */
    protected function getTimezone(): \DateTimeZone {
        return new \DateTimeZone('Europe/Berlin');
    }

    /** Converts a string to an integer.
     * @param string|null $string string to be converted
     * @param int|string $default value that should be returned if string is empty or null
     * @return int|string converted string or $default if string is empty or null
     */
    protected function getIntFromString(?string $string, int|string $default = ''): int|string {
        return ($string==='' || $string===null) ? $default : (int)($string);
    }

    /** Converts a string to a boolean.
     * @param string $string string to be converted
     * @return bool true if the string equals 'true' or '1', false otherwise
     */
    protected function getBoolFromString(string $string): bool {
        return $string==='true' || $string==='1';
    }

    /** Converts a boolean to a string.
     * @param bool $bool bool o be converted
     * @return string 'true' or 'false'
     */
    protected function getStringFromBool(bool $bool): string {
        return $bool ? 'true' : 'false';
    }

    /** Creates an array whose keys are the values of \$array prefixed by \$prefix. All keys and values are additionally prefixed with a string.
     * @param array $array array to be use
     * @param string $prefix string to be used as the prefix
     * @param string $valuePrefix if not an empty string, the values of the return array are the values prefixed by this string, otherwise suffixed by 'Text'
     * @return array array with the keys and values
     */
    protected function createPrefixArray(array $array, string $prefix = '', string $valuePrefix = ''): array {
        $returnArray = [];
        foreach ($array as $value) {
            $prefixedValue = $prefix.$value;
            $returnArray[$prefixedValue] = $valuePrefix==='' ? $this->appendText($prefixedValue) : $valuePrefix.$value;
        }
        return $returnArray;
    }

    /** Adds 'Div' to a string.
     * @param string $string string where 'Div' gets appended
     * @param bool $addMiddle if false, only 'Div' will be appended
     * @param bool $addText if true, 'Text' will be added before 'Div', otherwise 'Description'. Only used if $addMiddle is true
     * @return string $string with 'Div' appended
     */
    private function addDiv(string $string, bool $addMiddle = false, bool $addText = true): string {
        return $string.($addMiddle ? ($addText ? 'Text' : self::descriptionCap) : '').'Div';
    }

    /** Creates an array where each key is the concatenation of \$prefix and a value in \$values and the values are $values.
     * @param string $prefix first part of the keys
     * @param array $values values to appended to the prefix
     * @param bool $flip if true, then keys and values are flipped
     * @return array keys: concatenation of \$prefix and each value in \$values, values: $values
     */
    protected function translateArray(string $prefix, array $values, bool $flip = false): array {
        $returnArray = [];
        foreach ($values as $value) {
            $returnArray[$prefix.$value] = $value;
        }
        return $flip ? array_flip($returnArray) :$returnArray;
    }

    /** Translates a string.
     * @param string $string String to be translated. Must be a valid key in the translation file
     * @param array $parameters parameters for the translation.
     * @param string $domain domain to be used for the translation. Defaults to 'messages'
     * @return string the translated string
     */
    protected function translateString(string $string, array $parameters = [], string $domain = 'messages'): string {
        return self::$translator->trans($string, $parameters,$domain);
    }
}