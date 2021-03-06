<?php

namespace Acquia\Blt\Robo\Common;

use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * An extension of \Robo\Common\IO.
 */
trait IO {

  use \Robo\Common\IO;

  /**
   * Writes text to screen, without decoration.
   *
   * @param string $text
   *   The text to write.
   */
  protected function say($text) {
    $this->writeln($text);
  }

  /**
   * Writes text to screen with big, loud decoration.
   *
   * @param string $text
   *   The text to write.
   * @param int $length
   *   The length at which text should be wrapped.
   * @param string $color
   *   The color of the text.
   */
  protected function yell($text, $length = 40, $color = 'green') {
    $format = "<fg=white;bg=$color;options=bold>%s</fg=white;bg=$color;options=bold>";
    $this->formattedOutput($text, $length, $format);
  }

  /**
   * Format text as a question.
   *
   * @param string $message
   *   The question text.
   *
   * @return string
   *   The formatted question text.
   */
  protected function formatQuestion($message) {
    return "<question> $message</question> ";
  }

  /**
   * Prompts a user to confirm an action.
   *
   * This integrates the global --yes option and permits a default to be
   * defined.
   *
   * @param string $question
   *   The question text.
   * @param bool $default
   *   The default value, if user indicated --no-interaction.
   *
   * @return string
   *   The response.
   */
  protected function confirm($question, $default = FALSE) {
    if ($this->input()->hasOption('yes') && $this->input()->getOption('yes')) {
      return TRUE;
    }

    return $this->doAsk(new ConfirmationQuestion($this->formatQuestion($question . ' (y/n)'), $default));
  }

  /**
   * Asks the user a multiple-choice question.
   *
   * @param string $question
   *   The question text.
   * @param array $options
   *   An array of available options.
   *
   * @return string
   *   The chosen option.
   */
  protected function askChoice($question, $options, $default = NULL) {
    return $this->doAsk(new ChoiceQuestion($this->formatQuestion($question),
      $options, $default));
  }

  /**
   * Asks a required question.
   *
   * @param string $message
   *   The question text.
   *
   * @return string
   *   The response.
   */
  protected function askRequired($message) {
    $question = new Question($this->formatQuestion($message));
    $question->setValidator(function ($answer) {
      if (empty($answer)) {
        throw new \RuntimeException(
          'You must enter a value!'
        );
      }

      return $answer;
    });
    return $this->doAsk($question);
  }

}
