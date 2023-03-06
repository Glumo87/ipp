<?php

const INTEGER=1;
const STRING_LIT=2;
const BOOL=3;
const VARIABLE =4;
const NIL=5;
const LABEL=6;
const TYPE=7;
const INVALID=8;

const KEYWORD0ARGS=9;
const KEYWORD1ARGS=10;
const KEYWORD2ARGS=11;
const KEYWORD3ARGS=12;
const INVALIDHEADER=21;
const INVALIDOPCODE=22;
const INVALIDSYNTAX=23;

/*
 *
 *
 */
class Token {
    private string $data;
    private int $tokenType;

    public function __construct(string $data,int $tokenType) {
        $this->data=$data;
        $this->tokenType=$tokenType;
    }
    //unclean solution, which is used only for the "type" token type, as in lexical analysis cant
    //decide whether its a type or a label
    public function changeTokenType(int $type) : void {
        $this->tokenType=$type;
    }

    public function getTokenType() : int {
        return $this->tokenType;
    }
    //used when creating XML file, clips the string@ int@ bool@ etc.
    public function clipToken() : string {
        if ($this->getTokenType()==VARIABLE||$this->getTokenType()==LABEL||$this->getTokenType()==TYPE)
            return $this->getTokenData();
        else {
            $buffer=preg_split("/@/",$this->getTokenData());
            //echo $buffer[1],"\n";
            return $buffer[1];
        }
    }

    public function getTokenData() : string {
        return $this->data;
    }
    //method used to construct new tokens to have easier way to distinguish keywords
    public static function createToken(string $data, bool $isAKeyword) : Token {
        return new Token($data,Token::decideTokenType($data,$isAKeyword));
    }
    public static function tokenTypeToString(int $type) : string {
        switch ($type) {
            case INTEGER:
                return "int";
            case STRING_LIT:
                return "string";
            case BOOL:
                return "bool";
            case NIL:
                return "nil";
            case VARIABLE:
                return "var";
            case LABEL:
                return "label";
            case TYPE:
                return "type";
            default:
                return "invalid";
        }
    }
    //represented the decideTokenType methods as static rather than creating a new class specifically for it
    private static function decideTokenType(string $data,bool $isAKeyword) : int {
        if($isAKeyword) {
            return Token::decideKeyword($data);
        }
        else {
            return Token::decideOperand($data);
        }
    }
    private static function decideKeyword(string $data) : int {
        switch(strlen($data)) {
            case 2:
                return ((preg_match("/^lt$/i",$data))||(preg_match("/^gt$/i",$data))||
                    (preg_match("/^eq$/i",$data))||(preg_match("/^or$/i",$data))) ? KEYWORD3ARGS : INVALID;
            case 3:
                if ((preg_match("/^mul$/i",$data))||(preg_match("/^sub$/i",$data))||
                    (preg_match("/^add$/i",$data))||(preg_match("/^and$/i",$data)))
                    return KEYWORD3ARGS;
                else if (preg_match("/^not$/i",$data))
                    return KEYWORD2ARGS;
                else
                    return INVALID;
            case 4:
                if ((preg_match("/^exit$/i",$data))||(preg_match("/^call$/i",$data))||
                    (preg_match("/^jump$/i",$data))||(preg_match("/^pops$/i",$data)))
                    return KEYWORD1ARGS;
                else if((preg_match("/^move$/i",$data))||(preg_match("/^type$/i",$data))||
                    (preg_match("/^read$/i",$data)))
                    return KEYWORD2ARGS;
                else if (preg_match("/^idiv$/i",$data))
                    return KEYWORD3ARGS;
                else
                    return INVALID;
            case 5:
                if ((preg_match("/^pushs$/i",$data))||(preg_match("/^write$/i",$data))||
                    (preg_match("/^label$/i",$data)))
                    return KEYWORD1ARGS;
                else if (preg_match("/^break$/i",$data))
                    return KEYWORD0ARGS;
                else
                    return INVALID;
            case 6:
                if (preg_match("/^return$/i",$data))
                    return KEYWORD0ARGS;
                else if ((preg_match("/^dprint$/i",$data))||(preg_match("/^defvar$/i",$data)))
                    return KEYWORD1ARGS;
                else if (preg_match("/^strlen$/i",$data))
                    return KEYWORD2ARGS;
                else if (preg_match("/^concat$/i",$data))
                    return KEYWORD3ARGS;
                else
                    return INVALID;
            case 7:
                return ((preg_match("/^getchar$/i",$data))||(preg_match("/^setchar$/i",$data))) ? KEYWORD3ARGS : INVALID;
            case 8:
                if (preg_match("/^popframe$/i",$data))
                    return KEYWORD0ARGS;
                else if ((preg_match("/^int2char$/i",$data)))
                    return KEYWORD2ARGS;
                else if ((preg_match("/^stri2int$/i",$data))||(preg_match("/^jumpifeq$/i",$data)))
                    return KEYWORD3ARGS;
                else
                    return INVALID;
            case 9:
                if (preg_match("/^pushframe$/i",$data))
                    return KEYWORD0ARGS;
                else if ((preg_match("/^jumpifneq$/i",$data)))
                    return KEYWORD3ARGS;
                else
                    return INVALID;
            case 11:
                return (preg_match("/^createframe$/i",$data))?KEYWORD0ARGS:INVALID;
            default:
                return INVALID;
        }
    }
    private static function decideOperand(string $data) :int  {
        if(!strpos($data,"@")&&preg_match("/^[a-zA-Z_\-\$&%*!?]/",$data)) {
            return LABEL;
        }
        if(preg_match("/^GF@[a-zA-Z_\-\$&%*!?]/",$data)||preg_match("/^LF@[a-zA-Z_\-\$&%*!?]/",$data)||
            preg_match("/^TF@[a-zA-Z_\-\$&%*!?]/",$data)) {
            return VARIABLE;
        }
        if(preg_match("/^string@/",$data)) {
            return STRING_LIT;
        }
        if(preg_match("/^int@/",$data)) {
            return INTEGER;
        }
        if(preg_match("/^bool@true$/",$data)||preg_match("/^bool@false$/",$data)) {
            return BOOL;
        }
        if(preg_match("/^nil@/",$data)) {
            return NIL;
        }
        return INVALID;
    }
}
//in this class the syntax analysis takes place, each object represents one command of IPPcode23
//it is then later used to create XML file easily
class commandXML {
    private array $tokens;
    private bool $valid;
    private int $argCount;

    public function __construct(Token $token) {
        $this->tokens[0] = $token;
        $this->valid=!($token->getTokenType()===INVALID);
        if($this->valid)
            $this->argCount=$token->getTokenType()-KEYWORD0ARGS; //relies on constants from KEYWORD0ARGS to KEYWORD3ARGS
        // being one away from each other
        else
            $this->argCount=0;
    }
    public function returnValidity() :bool {
        return $this->valid;
    }

    public function returnArgCount() : int {
        return $this->argCount;
    }

    public function getToken(int $which) : Token {
        return $this->tokens[$which];
    }
    //addToken AKA the syntax analysis, very long method mostly due to the Error logging parts
    public function addToken(Token $token, int $which) : void {
        //start of the part where the obvious erros are being checked

        if($this->tokens[0]->getTokenType()===INVALID) {
            ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                "Invalid token ".$token->getTokenData()." on line ".IO::getInstance()->getLineCount()."\n");
            return;
        }
        if($which>3) {
            $this->valid=false;
            ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                "Too many arguments on line ".IO::getInstance()->getLineCount()."\n");
        }

        //start of the part where based on how many argumets for the keyword are expected the respective methods get called
        else if($this->tokens[0]->getTokenType()===KEYWORD0ARGS) {
            $this->valid=false; //error is thrown as this keyword cant have arguments
            ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                "Too many arguments on line ".IO::getInstance()->getLineCount()."\n");
        }
        else if($this->tokens[0]->getTokenType()===KEYWORD1ARGS) {
            if ($which >1) { //if we have more than one argument, error is thrown
                $this->valid=false;
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Too many arguments on line ".IO::getInstance()->getLineCount()."\n");
            }
            else {
                if($this->checkSyntax1Arg($this->tokens[0]->getTokenData(),$token->getTokenType())) {
                    $this->tokens[$which]=$token;
                }
                else {
                    $this->valid=false;
                }
            }
        }
        else if($this->tokens[0]->getTokenType()===KEYWORD2ARGS) {
            if ($which >2) { //if we have more than two arguments, error is thrown
                $this->valid=false;
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Too many arguments on line ".IO::getInstance()->getLineCount()."\n");
            }
            else {
                if ($this->checkSyntax2Arg($this->tokens[0]->getTokenData(), $which,$token)) {
                    $this->tokens[$which]=$token;
                }
                else {
                    $this->valid=false;
                }
            }
        }
        else { //no point in checking if there is more than 3 args as that was tested for at the start of the method
            if($this->checkSyntax3Arg($this->tokens[0]->getTokenData(),$token->getTokenType(),$which)) {
                $this->tokens[$which]=$token;
            }
            else {
                $this->valid=false;
            }
        }
    }
    private function checkSyntax1Arg(string $command,int $tokenType) :bool {
        /*
         * the keywords get grouped based on their syntax and tested
         * jump call label - <label>
         * pops defvar - <variable>
         * pushs dprint write <variable or literal>
         * exit <integer>
         */
        if((preg_match("/^j/i",$command))||(preg_match("/^c/i",$command))||(preg_match("/^l/i",$command))) {
            if(!$tokenType==LABEL) {
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                    " on line " . IO::getInstance()->getLineCount() . "\n");
                return false;
            }
            return true;
        }
        else if((preg_match("/^po/i",$command))||(preg_match("/^de/i",$command))) {
            if(!($tokenType==VARIABLE)) {
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                    " on line " . IO::getInstance()->getLineCount() . "\n");
                return false;
            }
            return true;
        }
        else if((preg_match("/^pu/i",$command))||(preg_match("/^dp/i",$command))||(preg_match("/^w/i",$command))) {
            if (!($tokenType>=INTEGER &&$tokenType<=NIL)) {
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                    " on line " . IO::getInstance()->getLineCount() . "\n");
                return false;
            }
            return true;
        }
        else {
            if(!($tokenType==INTEGER)) {
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                    " on line " . IO::getInstance()->getLineCount() . "\n");
                return false;
            }
            return true;
        }
    }
    private function checkSyntax2Arg(string $command,int $which,Token $token) :bool {
        /*
         * the keywords get grouped based on their syntax and tested
         * this method has different arguments to the other ones as in case of "read" the 2nd argument, which is expected
         * to be "type", is for simplicity represented as label from lexical analysis and only here it is possible to decide
         * if its of type "type"
         * move type - <variable> <variable or literal>
         * int2char - <variable> <variable or integer>
         * not - <variable> <variable or bool>
         * read <variable> <type>
         * strlen <variable> <variable or string>
         */
        $tokenType=$token->getTokenType();
        $tokenData=$token->getTokenData();
        if($which===1) {
            if(!($tokenType===VARIABLE)) {
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                    " on line " . IO::getInstance()->getLineCount() . "\n");
                return false;
            }
            return true;
        }
        else if($which===2) {
            if(preg_match("/^s/i",$command)) {
                if(!($tokenType==STRING_LIT||$tokenType==VARIABLE)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^r/i",$command)) {
                if(!($tokenType==LABEL&&($tokenData=="int"||$tokenData=="bool"||$tokenData=="string"))) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                $token->changeTokenType(TYPE);
                return true;
            }
            else if(preg_match("/^n/i",$command)) {
                if(!($tokenType==BOOL||$tokenType==VARIABLE)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^i/i",$command)) {
                if(!($tokenType==INTEGER||$tokenType==VARIABLE)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else {
                if(!($tokenType>=INTEGER && $tokenType<=NIL)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
        }
        return false;
    }
    private function checkSyntax3Arg(string $command,int $tokenType,int $which) :bool {
        /*
         * the keywords get grouped based on their syntax and tested
         * in case of eq and jumpifeq, jumpifneq, lt, gt the sameness of types needs to be enforced between arg2 and arg3
         * jumpifeq jumpifneq <label> <variable or literal> <vaiable or literal>
         * add sub mul idiv <variable> <variable or int> <variable or int>
         * eq <label> <variable or literal> <variable or literal>
         * lt gt <variable> <variable or literal - nil> <variable or literal - nil>
         * and or <variable> <variable or bool> <variable or bool>
         * setchar <variable> <int> <string>
         * getchar <variable> <string> <int>
         */
        if($which===1) {
            if(preg_match("/^j/i",$command)) {
                if(!($tokenType==LABEL)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            if(!($tokenType===VARIABLE)) {
                ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                    "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                    " on line " . IO::getInstance()->getLineCount() . "\n");
                return false;
            }
            return true;
        }
        else if($which===2) {
            if(preg_match("/^ad/i",$command)||preg_match("/^su/i",$command)||
                preg_match("/^m/i",$command)||preg_match("/^i/i",$command)||
                preg_match("/^se/i",$command)) {
                if($tokenType==VARIABLE||$tokenType==INTEGER) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
                }
            else if(preg_match("/^j/i",$command)||preg_match("/^e/i",$command)) {
                if(!($tokenType >=INTEGER||$tokenType <= NIL)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^l/i",$command)||preg_match("/^gt/i",$command)) {
                if(!($tokenType >=INTEGER||$tokenType <= VARIABLE)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^s/i",$command)||preg_match("/^c/i",$command)||
                preg_match("/^g/i",$command)) {
                if(!($tokenType===VARIABLE||$tokenType===STRING_LIT)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^an/i",$command)||preg_match("/^o/i",$command)) {
                if(!($tokenType===VARIABLE||$tokenType===BOOL)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else {
                return false;
            }
        }
        else {
            if(preg_match("/^ad/i",$command)||preg_match("/^su/i",$command)||
                preg_match("/^m/i",$command)||preg_match("/^i/i",$command)||
                preg_match("/^ge/i",$command)||preg_match("/^st/i",$command)) {
                if(!($tokenType===VARIABLE||$tokenType===INTEGER)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^j/i",$command)||preg_match("/^e/i",$command)) {
                $temp=$this->getToken($which-1)->getTokenType();
                if(!($tokenType==VARIABLE||$tokenType==NIL||
                  $temp==$tokenType||$temp==VARIABLE||$temp==NIL)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^l/i",$command)||preg_match("/^g/i",$command)) {
                $temp=$this->getToken($which-1)->getTokenType();
                if(!($tokenType===VARIABLE||$temp===$tokenType||
                    $temp===VARIABLE)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^s/i",$command)||preg_match("/^c/i",$command)) {
                if(!($tokenType===VARIABLE||$tokenType===STRING_LIT)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else if(preg_match("/^a/i",$command)||preg_match("/^o/i",$command)) {
                if(!($tokenType===VARIABLE||$tokenType===BOOL)) {
                    ErrorCollector::getInstance()->logError(INVALIDSYNTAX,
                        "Unexpected token type of " . Token::tokenTypeToString($tokenType) .
                        " on line " . IO::getInstance()->getLineCount() . "\n");
                    return false;
                }
                return true;
            }
            else {
                return false;
            }
        }
    }
}
//class for error collection, uses the singleton design pattern, also method finish is last method called
//at the end of the program as it contains "exit"
class ErrorCollector {
    private int $errorCode; //first error
    private array $errorMessages;
    private int $errorCount;
    private static ErrorCollector $error;
    private function __construct() {
        $this->errorCode=0;
        $this->errorCount=0;
        $this->errorMessages=array();
        return $this;
    }
    public function getErrorCount() :int {
        return $this->errorCount;
    }
    public static function createErrorCollector() : void {
        static $createdObject=false;
        if(!$createdObject) {
            ErrorCollector::$error=new ErrorCollector();
            $createdObject=true;
        }
    }
    public static function getInstance() : ErrorCollector {
        return ErrorCollector::$error;
    }
    public function logError(int $errorCode,string $errorMessage) :void {
        $this->errorCode=($this->errorCode==0)? $errorCode:$this->errorCode; //ensures that first error code will be
        // the return value
        $this->errorMessages[$this->errorCount++]=$errorMessage;
    }
    public function finish() : void {
        for($i=0;$i<$this->errorCount;$i++) {
            fwrite(STDERR,$this->errorMessages[$i]);
        }
        exit($this->errorCode);
    }
}
//another singleton class, used for reading input and outputting XML file
class IO {
    private static IO $io;
    private int $lineCount;
    private function __construct() {
        $this->lineCount=0;
        return $this;
    }
    public static function createIO() : void {
        static $createdObject=false;
        if(!$createdObject) {
            IO::$io=new IO();
            $createdObject=true;
        }
    }
    public static function getInstance() : IO {
        return IO::$io;
    }
    private function incrementLineCount() : void {
        $this->lineCount++;
    }
    public function getLineCount() : int {
        return $this->lineCount;
    }
    //this method not only reads next line but also splits the tokens into an array for easier manipulation later
    //also ignores the comments
    public function readNextLine() : array {
        do {
            $buffer=fgets(STDIN,4096);
            $this->incrementLineCount();
            if(strlen($buffer)==0)
                return array();
        } while($buffer[0]==="#"||($buffer && !trim($buffer)));
        $buffer=preg_split("/#+/",$buffer);
        $buffer= preg_replace('/\s+/','#',$buffer[0]);
        $buffer= preg_replace('/#$/','',$buffer);
        return preg_split("/#+/",$buffer);
    }

    public function assertHeader() : bool {
        $header = $this->readNextLine();
        if(!preg_match("/^\.IPPcode23$/",$header[0])) {
            return false;
        }
        return true;
    }

    public function readSourceFile() :array {
        //reads stdin until EOF
        $j=0;
        $Xml=array();
        while(count($buffer=$this->readNextLine())!==0) {
            $token = Token::createToken($buffer[0],true);
            $Xml[$j]=new commandXML($token);
            if (!($Xml[$j]->returnValidity()===true)) {
                ErrorCollector::getInstance()->logError(INVALIDOPCODE,
                    "Invalid opcode ".$Xml[$j]->getToken(0)->getTokenData()." on line ".$this->getLineCount()."\n");
            }
            for ($i=1;$i<count($buffer);$i++) {
                $Xml[$j]->addToken(Token::createToken($buffer[$i],false),$i);
            }
            $j++;
        }
        return $Xml;
    }

    private function createXMLHeader() : ?DOMDocument {
        $doc = new DOMDocument();
        $doc->encoding='utf-8';
        $doc->xmlVersion = '1.0';
        $doc->formatOutput = true;
        return $doc;
    }
    //fairly straightforward method to create xml file, reads an array of commands, each command consists of tokens
    //one XMLCommand object = one instruction element
    public function createXMLFile(array $Xml) :void {
        $doc = $this->createXMLHeader();
        $root =$doc->createElement('program');
        $program_attr= new DOMAttr('language','IPPcode23');
        $root->setAttributeNode($program_attr);
        $doc->appendChild($root);
        for($i=0;$i<count($Xml);$i++) {
            $instruction_node=$doc->createElement('instruction');
            $instruction_node_attr1= new DOMAttr('order',(string)($i+1));
            $instruction_node_attr2= new DOMAttr('opcode',$Xml[$i]->getToken(0)->getTokenData());
            $instruction_node->setAttributeNode($instruction_node_attr1);
            $instruction_node->setAttributeNode($instruction_node_attr2);
            for($j=0;$j<$Xml[$i]->returnArgCount();$j++) {
                $string="arg1";
                switch($j) {
                    case 1:
                        $string="arg2";
                        break;
                    case 2:
                        $string="arg3";
                        break;
                    default:
                        break;
                }
                $argument_node=$doc->createElement($string,htmlspecialchars($Xml[$i]->getToken($j+1)->clipToken()));
                $argument_node_att=new DOMAttr('type',
                    Token::tokenTypeToString($Xml[$i]->getToken($j+1)->getTokenType()));
                $argument_node->setAttributeNode($argument_node_att);
                $instruction_node->appendChild($argument_node);
            }
            $root->appendChild($instruction_node);
        }
        echo $doc->saveXML(null,LIBXML_NOEMPTYTAG);
    }
}
//the "main" part of the program, two singletons get instantiated, then an array of XMLCommands
// get created, which, if no syntax or lexical error is found, is then passed to IO class to create XML
ErrorCollector::createErrorCollector();
IO::createIO();
if(!IO::getInstance()->assertHeader())
    ErrorCollector::getInstance()->logError(INVALIDHEADER,
        "Invalid Header\n");
$Xml = IO::getInstance()->readSourceFile();

if(ErrorCollector::getInstance()->getErrorCount()==0)
    IO::getInstance()->createXMLFile($Xml);
ErrorCollector::getInstance()->finish();

?>