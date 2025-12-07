# Changelog

All notable changes to this project will be documented in this file.

## [0.7.0]
Added: 
- `ZJKiza\HttpResponseValidator\Handler\ArrayStructureValidateExactHandler` – validates the structure of the response using strict/exact key and type checking
- `ZJKiza\HttpResponseValidator\Handler\ArrayStructureValidateInternalHandler` – validates the structure using internal/relaxed rules for key existence and potential type checking

**!BREAKING CHANGE!**: removed 
- `ZJKiza\HttpResponseValidator\Handler\ValidateArrayKeysExistHandler`

## [0.6.0]
Added
- When ValidateArrayKeysExistHandler encounters the first error in the keys, it does not immediately throw an exception, but collects all the missing keys and only then throws the exception.

Fixed:
- remove getRequestOptions  

## [0.5.1]
Fixed:
- tag name
- documentation

## [0.5.0]
Initial version