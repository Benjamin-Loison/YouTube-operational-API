#!/usr/bin/python3

## /!\ Assume that the content of `curl.txt` is trusted /!\

'''
For the moment this algorithm only:
- removes unnecessary headers
- removes unnecessary URL parameters
'''

import shlex, subprocess, json, copy
from urllib.parse import urlparse, parse_qs, quote_plus

wantedOutput = '1714'

# Pay attention to provide a command giving plaintext output, so might required to remove `Accept-Encoding` HTTPS header.
with open('curl.txt') as f:
    command = f.read()

def executeCommand(command):
    # `stderr = subprocess.DEVNULL` is used to get rid of curl progress.
    result = subprocess.check_output(f'{command}', shell = True, stderr = subprocess.DEVNULL).decode('utf-8')
    return result

def isCommandStillFine(command):
    result = executeCommand(command)
    return wantedOutput in result
    '''data = json.loads(result)
    accessToken = data['access_token']
    command = f"curl https://URL -H 'Authorization: Bearer {accessToken}'"
    result = executeCommand(command)
    return wantedOutput in result'''

print(len(command))

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

print('Removing URL parameters')

arguments = shlex.split(command)
for argumentsIndex, argument in enumerate(arguments):
    if argument.startswith('http'):
        urlIndex = argumentsIndex
        break

url = arguments[urlIndex]
while True:
    changedSomething = False
    #print(url)
    urlParsed = urlparse(url)
    query = parse_qs(urlParsed.query)
    #print(query)
    for key in list(query):
        previousQuery = copy.deepcopy(query)
        #print(key)
        #print(key, query[key])
        del query[key]
        url = urlParsed._replace(query = '&'.join([f'{parameter}={quote_plus(query[parameter][0])}' for parameter in query])).geturl()
        #print(url)
        arguments[urlIndex] = url
        command = shlex.join(arguments)
        if isCommandStillFine(command):
            print(len(command), 'still fine')
            changedSomething = True
            break
        else:
            #print(len(command), 'not fine')
            query = previousQuery
            url = urlParsed._replace(query = '&'.join([f'{parameter}={quote_plus(query[parameter][0])}' for parameter in query])).geturl()
            arguments[urlIndex] = url
            command = shlex.join(arguments)
    if not changedSomething:
        break

with open('minimizedCurl.txt', 'w') as f:
    f.write(command)
