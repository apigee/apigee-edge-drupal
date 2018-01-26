<?php

namespace Drupal\apigee_edge\Command;

use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Redirects Drupal logging messages to Drupal Console log.
 */
class DrupalConsoleLog implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $logMessageParser;

  /**
   * The logger that messages will be passed through to.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a DrupalConsoleLog object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $log_message_parser
   *   The parser to use when extracting message variables.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger instance.
   */
  public function __construct(LogMessageParserInterface $log_message_parser, LoggerInterface $logger) {
    $this->logMessageParser = $log_message_parser;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    switch ($level) {
      case RfcLogLevel::ERROR:
        $error_type = LogLevel::ERROR;
        break;

      case RfcLogLevel::WARNING:
        $error_type = LogLevel::WARNING;
        break;

      case RfcLogLevel::DEBUG:
        $error_type = LogLevel::DEBUG;
        break;

      case RfcLogLevel::INFO:
        $error_type = LogLevel::INFO;
        break;

      case RfcLogLevel::NOTICE:
        $error_type = LogLevel::NOTICE;
        break;

      default:
        $error_type = $level;
        break;
    }

    $message_placeholders = $this->logMessageParser->parseMessagePlaceholders($message, $context);
    $message_placeholders = array_filter($message_placeholders, function ($element) {
      return is_scalar($element) || is_callable([$element, '__toString']);
    });
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

    $this->logger->log($error_type, $message, $context);
  }

}
