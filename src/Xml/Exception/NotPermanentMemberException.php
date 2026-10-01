<?php

/*
 * This file is part of the QuipXml package.
 *
 * (c) Greg Payne <1994413+stackpr@users.noreply.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QuipXml\Xml\Exception;

/**
 * Thrown when a node that does not exist in the document is written to.
 *
 * SimpleXML returns a placeholder for a missing child (`$quip->missing`);
 * writing markup or text into it has nowhere to go. get() creates the
 * missing path first.
 */
class NotPermanentMemberException extends \ErrorException {

  /**
   * Creates the exception with a default message.
   *
   * @param string|null $message
   *   The message, or NULL for the default.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable, if any.
   */
  public function __construct(?string $message = NULL, int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message ?? 'Parent is not a permanent member of the XML tree. See QuipXmlElement::get()', $code, E_ERROR, NULL, NULL, $previous);
  }

}
