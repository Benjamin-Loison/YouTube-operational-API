#!/usr/bin/python3

## /!\ Assume that the content of `curlCommandFilePath` is trusted /!\
# TODO: precising or/and lowering this trust level would be interesting
# Note that this algorithm doesn't currently minimize specifically YouTube requests (like `--data-raw/context/client/clientVersion`), should potentially add in the future useless character removal which would be a more general but incomplete approach.

'''
For the moment this algorithm only removes unnecessary:
- headers
- URL parameters
- cookies
- raw data
'''

import shlex, subprocess, json, copy, sys
from urllib.parse import urlparse, parse_qs, quote_plus

# Could precise the input file and possibly remove the output one as the minimized requests start to be short.
if len(sys.argv) < 3:
    print('Usage: ./minimizeCURL curlCommand.txt "Wanted output"')
    exit(1)

curlCommandFilePath = sys.argv[1]
wantedOutput = sys.argv[2]

# The purpose of these parameters is to reduce requests done when developing this script:
removeHeaders = True
removeUrlParameters = True
removeCookies = True
removeRawData = True

# Pay attention to provide a command giving plaintext output, so might required to remove `Accept-Encoding` HTTPS header.
with open(curlCommandFilePath) as f:
    command = f.read()

def executeCommand(command):
    # `stderr = subprocess.DEVNULL` is used to get rid of curl progress.
    # Could also add `-s` curl argument.
    result = subprocess.check_output(f'{command}', shell = True, stderr = subprocess.DEVNULL).decode('utf-8')
    return result

def isCommandStillFine(command):
    result = executeCommand(command)
    return wantedOutput in result

print(len(command))
# To verify that the user provided the correct `wantedOutput` to keep during the minimization.
if not isCommandStillFine(command):
    print('The wanted output isn\'t contained in the result of the original curl command!')
    exit(1)

if removeHeaders:
    print('Removing headers')

    # Should try to minimize the number of requests done, by testing half of parameters at each request.
    while True:
        changedSomething = False
        arguments = shlex.split(command)
        for argumentsIndex in range(len(arguments) - 1):
            argument, nextArgument = arguments[argumentsIndex : argumentsIndex + 2]
            if argument == '-H':
                previousCommand = command
                del arguments[argumentsIndex : argumentsIndex + 2]
                command = shlex.join(arguments)
                if isCommandStillFine(command):
                    print(len(command), 'still fine')
                    changedSomething = True
                    break
                else:
                    command = previousCommand
                    arguments = shlex.split(command)
        if not changedSomething:
            break

if removeUrlParameters:
    print('Removing URL parameters')

    arguments = shlex.split(command)
    for argumentsIndex, argument in enumerate(arguments):
        if argument.startswith('http'):
            urlIndex = argumentsIndex
            break

    url = arguments[urlIndex]
    while True:
        changedSomething = False
        urlParsed = urlparse(url)
        query = parse_qs(urlParsed.query)
        for key in list(query):
            previousQuery = copy.deepcopy(query)
            del query[key]
            url = urlParsed._replace(query = '&'.join([f'{parameter}={quote_plus(query[parameter][0])}' for parameter in query])).geturl()
            arguments[urlIndex] = url
            command = shlex.join(arguments)
            if isCommandStillFine(command):
                print(len(command), 'still fine')
                changedSomething = True
                break
            else:
                query = previousQuery
                url = urlParsed._replace(query = '&'.join([f'{parameter}={quote_plus(query[parameter][0])}' for parameter in query])).geturl()
                arguments[urlIndex] = url
                command = shlex.join(arguments)
        if not changedSomething:
            break

if removeCookies:
    print('Removing cookies')

    COOKIES_PREFIX = 'Cookie: '

    cookiesIndex = None
    arguments = shlex.split(command)
    for argumentsIndex, argument in enumerate(arguments):
        if argument.startswith(COOKIES_PREFIX):
            cookiesIndex = argumentsIndex
            break

    if cookiesIndex is not None:
        cookies = arguments[cookiesIndex]
        while True:
            changedSomething = False
            cookiesParsed = cookies.replace(COOKIES_PREFIX, '').split('; ')
            for cookiesParsedIndex, cookie in enumerate(cookiesParsed):
                cookiesParsedCopy = cookiesParsed[:]
                del cookiesParsedCopy[cookiesParsedIndex]
                arguments[cookiesIndex] = COOKIES_PREFIX + '; '.join(cookiesParsedCopy)
                command = shlex.join(arguments)
                if isCommandStillFine(command):
                    print(len(command), 'still fine')
                    changedSomething = True
                    cookies = '; '.join(cookiesParsedCopy)
                    break
                else:
                    arguments[cookiesIndex] = COOKIES_PREFIX + '; '.join(cookiesParsed)
                    command = shlex.join(arguments)
            if not changedSomething:
                break

if removeRawData:
    print('Removing raw data')

    rawDataIndex = None
    isJson = False
    arguments = shlex.split(command)
    for argumentsIndex, argument in enumerate(arguments):
        if argumentsIndex > 0 and arguments[argumentsIndex - 1] == '--data-raw':
            rawDataIndex = argumentsIndex
            try:
                json.loads(argument)
                isJson = True
            except:
                pass
            break

    if rawDataIndex is not None:
        rawData = arguments[rawDataIndex]
        # Could interwine both cases but don't seem to clean much the code due to `getPaths` notably.
        # Just firstly making a common function to all parts minimizer would make sense.
        if not isJson:
            while rawData != '':
                changedSomething = False
                rawDataParts = rawData.split('&')
                for rawDataPartsIndex, rawDataPart in enumerate(rawDataParts):
                    rawDataPartsCopy = copy.deepcopy(rawDataParts)
                    del rawDataPartsCopy[rawDataPartsIndex]
                    arguments[rawDataIndex] = '&'.join(rawDataPartsCopy)
                    command = shlex.join(arguments)
                    if isCommandStillFine(command):
                        print(len(command), 'still fine')
                        changedSomething = True
                        rawData = '&'.join(rawDataPartsCopy)
                        break
                    else:
                        arguments[rawDataIndex] = '&'.join(rawDataParts)
                        command = shlex.join(arguments)
                if not changedSomething:
                    break
        else:
            def getPaths(d):
                if isinstance(d, dict):
                    for key, value in d.items():
                        yield f'/{key}'
                        yield from (f'/{key}{p}' for p in getPaths(value))

                elif isinstance(d, list):
                    for i, value in enumerate(d):
                        yield f'/{i}'
                        yield from (f'/{i}{p}' for p in getPaths(value))

            while True:
                changedSomething = False
                rawDataParsed = json.loads(rawData)
                # Note that the path goes from parents to children which is quite a wanted behavior to quickly remove useless chunks.
                paths = getPaths(rawDataParsed)
                for pathsIndex, path in enumerate(paths):
                    rawDataParsedCopy = copy.deepcopy(rawDataParsed)
                    entry = rawDataParsedCopy
                    pathParts = path[1:].split('/')
                    for pathPart in pathParts[:-1]:
                        pathPart = pathPart if not pathPart.isdigit() else int(pathPart)
                        entry = entry[pathPart]
                    lastPathPart = pathParts[-1]
                    lastPathPart = lastPathPart if not lastPathPart.isdigit() else int(lastPathPart)
                    del entry[lastPathPart]
                    arguments[rawDataIndex] = json.dumps(rawDataParsedCopy)
                    command = shlex.join(arguments)
                    if isCommandStillFine(command):
                        print(len(command), 'still fine')
                        changedSomething = True
                        rawData = json.dumps(rawDataParsedCopy)
                        break
                    else:
                        arguments[rawDataIndex] = json.dumps(rawDataParsed)
                        command = shlex.join(arguments)
                if not changedSomething:
                    break

command = command.replace(' --compressed', '')
command = command.replace(' --data-raw \'\'', '')

HTTP_METHOD = ' -X POST'

if HTTP_METHOD in command:
    previousCommand = command
    command = command.replace(HTTP_METHOD, '')
    if not isCommandStillFine(command):
        command = previousCommand

# First test `print`ing, before potentially removing `minimized_curl` writing.
print(command)
with open('minimized_curl.sh', 'w') as f:
    f.write(command)
