const fs=require('fs');
let ts;try{ts=require('typescript')}catch(e){console.log(JSON.stringify({status:'UNAVAILABLE',reason:'typescript module unavailable'}));process.exit(0)}
const file=process.argv[2];if(!file){console.log(JSON.stringify({status:'FAIL',reason:'file required'}));process.exit(0)}
const options={target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.ESNext,jsx:ts.JsxEmit.ReactJSX,allowJs:true};
const program=ts.createProgram([file],options);const checker=program.getTypeChecker();const source=program.getSourceFile(file);const symbols=[];const refs=[];
function visit(n){
 if((ts.isFunctionDeclaration(n)||ts.isClassDeclaration(n)||ts.isInterfaceDeclaration(n)||ts.isTypeAliasDeclaration(n)||ts.isVariableDeclaration(n))&&n.name){
   const sym=checker.getSymbolAtLocation(n.name);let type='unknown';try{type=checker.typeToString(checker.getTypeAtLocation(n))}catch(e){}
   symbols.push({name:n.name.getText(),kind:ts.SyntaxKind[n.kind],type,exported:!!(sym&&sym.getFlags())});
 }
 if(ts.isCallExpression(n)&&ts.isIdentifier(n.expression))refs.push({call:n.expression.text,line:source.getLineAndCharacterOfPosition(n.getStart()).line+1});
 ts.forEachChild(n,visit)
}
if(source)visit(source);
const diagnostics=ts.getPreEmitDiagnostics(program).map(d=>({code:d.code,message:ts.flattenDiagnosticMessageText(d.messageText,' '),file:d.file&&d.file.fileName,line:d.file?d.file.getLineAndCharacterOfPosition(d.start||0).line+1:null}));
console.log(JSON.stringify({status:'PASS',mode:'FULL_COMPILER',symbols,references:refs,diagnostics}));
