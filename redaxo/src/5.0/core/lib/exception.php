<?php

class rexException extends Exception
{
  public function __construct($message, $code = E_USER_ERROR, $previous = null)
  {
    parent:: __construct($message, $code, $previous);
  }
}