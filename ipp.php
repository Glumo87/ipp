<?php

const INTEGER=1;
const STRING_LIT=2;
const FLOATING_POINT=3;
const VARIABLE =4;
const NIL=5;
const LABEL=6;
const INVALID=7;
const KEYWORD0ARGS=9;
const KEYWORD1ARGS=10;
const KEYWORD2ARGS=11;
const KEYWORD3ARGS=12;

function readNextLine() : array {
    do {
        $buffer=fgets(STDIN,4096);
        if(strlen($buffer)==0)
            return array();
    } while($buffer[0]==="#");
    if (preg_match("/[^a-zA-Z\d_\-\$&%*!?@\s.\\\#]/",$buffer)) {
        return readNextLine();
    }
    $buffer=preg_split("/#+/",$buffer);
    $buffer= preg_replace('/\s+/','#',$buffer[0]);
    $buffer= preg_replace('/#$/','',$buffer);
    $buffer=preg_split("/#+/",$buffer);
    return $buffer;
}

class Token {
    private ?string $data;
    private ?int $tokenType;

    public function __construct(string $data,int $tokenType) {
        $this->data=$data;
        $this->tokenType=$tokenType;
    }

    public function getTokenType() : int {
        return $this->tokenType;
    }

    public function getTokenData() : string {
        return $this->data;
    }

    public static function createToken(string $data, bool $isAKeyword) : Token {
        return new Token($data,Token::decideTokenType($data,$isAKeyword));
    }
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
        if(preg_match("/^float@/",$data)) {
            return FLOATING_POINT;
        }
        if(preg_match("/^nil@/",$data)) {
            return NIL;
        }
        return INVALID;
    }
}

class commandXML {
    private ?Token $tokens;
    private ?bool $valid;


    public function __construct(Token $token) {
        $this->tokens[0] = $token;
        echo "in constructor XML\n";
        $this->valid=!($token->getTokenType()===INVALID);
    }
    public function returnValidity() :bool {
        return $this->valid;
    }
    public function getToken(int $which) : Token {
        return $this->tokens[$which];
    }
    public function addToken(Token $token, int $which) : void {
        echo "trying to add token ",$token->getTokenData()," on position: ",$which," of type ",$token->getTokenType(),"\n";
        if($this->tokens[0]->getTokenType()===INVALID) {
            return;
        }
        if($which>3) {
            $this->valid=false;
            echo "error";
        }
        else if($this->tokens[0]->getTokenType()===KEYWORD0ARGS) {
            $this->valid=false;
            echo "error";
        }
        else if($this->tokens[0]->getTokenType()===KEYWORD1ARGS) {
            if ($which >1) {
                $this->valid=false;
                echo "error";
            }
            else {
                if(CommandXML::checkSyntax1Arg($this->tokens[0]->getTokenData(),$token->getTokenType())) {
                    $this->tokens[$which]=$token;
                }
                else {
                    $this->valid=false;
                    echo "error";
                }
            }
        }
        else if($this->tokens[0]->getTokenType()===KEYWORD2ARGS) {
            if ($which >2) {
                $this->valid=false;
                echo "error";
            }
            else {
                if (CommandXML::checkSyntax2Arg($this->tokens[0]->getTokenData(),$token->getTokenType(),$which)) {
                    $this->tokens[$which]=$token;
                }
                else {
                    $this->valid=false;
                    echo "error";
                }
            }
        }
        else {
            if(CommandXML::checkSyntax3Arg($this->tokens[0]->getTokenData(),$token->getTokenType(),$which)) {
                $this->tokens[$which]=$token;
            }
            else {
                $this->valid=false;
                echo "error";
            }
        }
    }
    private static function checkSyntax1Arg(string $command,int $tokenType) :bool {
        /*
         * jump call label - <label>
         * pops defvar - <variable>
         * pushs dprint write <variable or literal>
         * exit <integer>
         */
        if((preg_match("/^j/i",$command))||(preg_match("/^c/i",$command))||(preg_match("/^l/i",$command))) {
            return ($tokenType===LABEL);
        }
        else if((preg_match("/^po/i",$command))||(preg_match("/^de/i",$command))) {
            return ($tokenType===VARIABLE);
        }
        else if((preg_match("/^pu/i",$command))||(preg_match("/^dp/i",$command))||(preg_match("/^w/i",$command))) {
            return ($tokenType>=INTEGER &&$tokenType<=NIL);
        }
        else {
            return($tokenType===INTEGER);
        }
    }
    private static function checkSyntax2Arg(string $command,int $tokenType,int $which) :bool {
        /*
         * not move type int2char - <variable> <variable or literal>
         * read <variable> <type>
         * strlen <variable> <variable or string>
         */
        if($which===1) {
            return($tokenType===VARIABLE);
        }
        else if($which===2) {
            if(preg_match("/^s/i",$command)) {
                return($tokenType==STRING_LIT||$tokenType==VARIABLE);
            }
            else if(preg_match("/^r/i",$command)) {
                return($tokenType==STRING_LIT);
            }
            else {
                return($tokenType>=INTEGER && $tokenType<=NIL);
            }
        }
        return false;
    }
    private static function checkSyntax3Arg(string $command,int $tokenType,int $which) :bool {
        /*
         * jumpifeq jumpifneq <label> <variable or literal> <vaiable or literal>
         * everything else <variable> <variable or literal> <variable or literal>
         */
        if($which===1) {
            if(preg_match("/^j/i",$command)) {
                return($tokenType===LABEL);
            }
            return($tokenType===VARIABLE);
        }
        else {
            return($tokenType >=INTEGER||$tokenType <= NIL);
        }
    }
}

$header = readNextLine();
if(!preg_match("/^\.IPPcode23$/",$header[0])) {
    echo "error";
}
$j=0;
while(count($buffer=readNextLine())!==0) {
    $token = Token::createToken($buffer[0],true);
    $Xml[$j]=new commandXML($token);
    for ($i=1;$i<count($buffer);$i++) {
        $Xml[$j]->addToken(Token::createToken($buffer[$i],false),$i);
    }
    print_r($buffer);
    $j++;
}
for($i=0;$i<count($Xml);$i++) {
    echo ($Xml[$i]->returnValidity()===true)?"true":"false";
}

/*echo $buffer,":";
echo strlen($buffer),"\n";
*/

?>