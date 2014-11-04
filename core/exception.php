<?php

class CustomException extends Exception
{
  public function __construct($message = null, $code = 0)
  {
    if (is_null($message)) {
      throw new $this('Unknown '. get_class($this));
    }
    parent::__construct($message, $code);
  }
   
  public function __toString()
  {
    return get_class($this) . " '{$this->message}/{$this->code}' in {$this->file}({$this->line})\n"
    . "{$this->getTraceAsString()}";
  }
  
  protected function dumpAction()
  {
  }
  
  
    
  public function dump()
  {
    $t = htmlspecialchars($this->getTraceAsString());
    $c = get_class($this);
    $ds = Template::dumpdebugstack();
    echo <<<EOT
<h1>Exception caught: $c</h1>
<h2>Message:</h2><pre>{$this->getMessage()}</pre>
<h2>Code:</h2><pre>{$this->getCode()}</pre>
<h2>PHP Trace:</h2><pre>$t</pre>
<h2>Template Trace:</h2><p>$ds</p>
EOT;
    $this->dumpAction();
  }
}


///////////////////////////////////////////////////////////////////////////////

class BadInstallException extends CustomException{}
class InternalException extends CustomException{}
class InvalidCollectionHandleException extends CustomException{}
class UnknownCollectionFieldSetException extends CustomException{}
class UnknownCollectionFieldGetException extends CustomException{}
class TemplateDirectoryNotFoundException extends CustomException{}
class BadStyleMapSyntaxException extends CustomException{}
class UnknownLanguageException  extends CustomException{}
class NoMainHtmlInDirectoryException extends CustomException{}
class UnknownBinopException extends CustomException{}
class NotEnoughArgumentsException extends CustomException{}
class BadArgumentException extends CustomException{}
class ModuleException extends CustomException{}
class TemplateFileNotFoundException extends CustomException{}
class CollectionInsideCollectionException extends CustomException{}
class SyntaxException extends CustomException{}
class RequirementMissingException extends CustomException{}
class InvalidTreeHandleException extends CustomException{}
class BadDefineException extends CustomException{}
class BadNavigationException extends CustomException{}
class StackOverflowException extends CustomException{}

class UnknownTagException extends CustomException
{
  protected function dumpAction()
  {
    print "<h1>Dump of all tags:</h1>";
    print Template::dump();
  }
}
  
