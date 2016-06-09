<?php

namespace ManifestoContext;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\MinkExtension\Context\RawMinkContext;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Mink\Driver\Selenium2Driver;

/**
 * Extend General Context with additional step-definitions.
 *
 * @author Gabriele Maira <gabriele.maira@manifesto.co.uk>
 *
 * @release 1.0
 */
class ExtendGeneralContext extends RawMinkContext implements SnippetAcceptingContext {

  /**
   * Path to reports where to dump screenshots and html.
   *
   * @var string
   */
  protected $reports_path;

  /**
   * ExtendGeneralContext constructor.
   */
  public function __construct() {

    $this->reports_path = (defined('BDD_REPORT_PATH'))
      ? BDD_REPORT_PATH
      : './';

  }

  /**
   * General before step.
   *
   * @BeforeStep
   */
  public function beforeStep() {
    try {
      $this->getSession()->getDriver()->resizeWindow(1440, 1024, 'current');
    }
    catch (UnsupportedDriverActionException $e) {
      // What can I do if driver doesn't support resizeWindow()?
      NULL;
    }
  }

  /**
   * Take a screenshot after a failure.
   *
   * @param AfterStepScope $scope
   *   The current scope.
   *
   * @AfterStep
   */
  public function takeScreenshotAfterFailedStep(AfterStepScope $scope) {
    if (TestResult::FAILED === $scope->getTestResult()->getResultCode()) {
      $this->takeScreenshot('failure');
      $this->dumpHTML();
    }
  }

  /**
   * Take a screenshot.
   *
   * @param string $status
   *   The status passed as a string will be appended to the filename.
   *
   * @throws \Exception
   *
   * @Then /^take a screenshot$/
   */
  public function takeScreenshot($status = 'screenshot') {
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      return;
    }
    $fileName = date('Y-m-d_Hi') . '-' . uniqid() . '-' . $status . '.png';
    $filePath = $this->reports_path . 'screenshots/';

    if (!file_exists($filePath)) {
      if (!mkdir($filePath)) {
        throw new \Exception('Failed to create ' . $filePath . ' folder');
      }
    }

    $this->saveScreenshot($fileName, $filePath);

  }

  /**
   * Helper to dump current page HTML.
   *
   * @throws \Exception
   */
  private function dumpHTML() {
    $html = $this->getSession()->getPage()->getContent();
    $fileName = 'dump-' . date('Y-m-d_Hi') . '-' . uniqid() . '.html';

    $htmlCapturePath = $this->reports_path . 'dump/';
    if (!file_exists($htmlCapturePath)) {
      if (!mkdir($htmlCapturePath)) {
        throw new \Exception('Failed to create ' . $htmlCapturePath . ' folder');
      }
    }

    file_put_contents($htmlCapturePath . $fileName, $html);
  }

  /**
   * Step to wait few seconds.
   *
   * @When I wait for :arg1 second(s)
   */
  public function iWaitForSecond($seconds) {
    sleep($seconds);
  }

  /**
   * Focus on a element with jQuery.
   *
   * @When I focus on :element
   */
  public function iFocusOnElement($element) {
    $this->getSession()->evaluateScript('jQuery( ":contains(\'' . $element . '\')" ).click()');
  }

  /**
   * Fill a field with a random string.
   *
   * @When I fill in :field with :value plus random data
   */
  public function fillFieldAppendRandom($field, $value) {
    $this->getSession()->getPage()->fillField($field, $value . substr(uniqid(), 0, 10));
  }

  /**
   * Selects an option using javascript.
   *
   * This method uses javascript to allow selection of options
   * specified by id|name|label|value that may be
   * overridden by javascript libraries, and thus hide the element.
   *
   * @throws ElementNotFoundException
   *
   * @When /^(?:|I )select "(?P<option>(?:[^"]|\\")*)" from "(?P<select>(?:[^"]|\\")*)" with javascript$/
   */
  public function selectOptionWithJavascript($select, $option) {
    $select = $this->fixStepArgument($select);
    $option = $this->fixStepArgument($option);
    $page = $this->getSession()->getPage();

    // Find field.
    $field = $page->findField($select);

    if (NULL === $field) {
      throw new ElementNotFoundException($this->getSession(), 'form field', 'id|name|label|value', $select);
    }

    // Find option.
    $opt = $field->find('named', array('option', $option));

    if (NULL === $opt) {
      throw new ElementNotFoundException($this->getSession(), 'select option', 'value|text', $option);
    }

    // Merge new option in with old handling both multiselect and single select.
    $value = $field->getValue();
    $newValue = $opt->getAttribute('value');

    if (is_array($value)) {
      if (!in_array($newValue, $value)) {
        $value[] = $newValue;
      }
    }
    else {
      $value = $newValue;
    }
    $valueEncoded = json_encode($value);

    // Inject this value via javascript.
    $fieldID = $field->getAttribute('ID');
    $script = <<<EOS
			(function($) {
				$("#$fieldID")
					.val($valueEncoded)
					.change()
					.trigger('liszt:updated')
					.trigger('chosen:updated');
			})(jQuery);
EOS;
    $this->getSession()->getDriver()->executeScript($script);
  }

  /**
   * Checks checkbox with specified id|name|label|value.
   *
   * @When /^(?:|I )check "(?P<option>(?:[^"]|\\")*)" with javascript$/
   */
  public function checkOption($option) {
    $option = $this->fixStepArgument($option);
    $field = $this->getSession()->getPage()->findField($option);

    $fieldID = $field->getAttribute('id');
    if (empty($fieldID)) {
      throw new \Exception('Field ID not found!');
    }

    $script = <<<EOS
			(function($) {
				$("#$fieldID").click();
			})(jQuery);
EOS;
    $this->getSession()->getDriver()->executeScript($script);
  }

  /**
   * Unescapes " (double quotes) in string.
   *
   * @param string $string
   *   The argument to be unescaped.
   *
   * @return string
   *   The unescaped string.
   */
  protected function fixStepArgument($string) {
    return str_replace('\\"', '"', $string);
  }

}
