#!/bin/bash

set -euo pipefail

# Handle all file content replacements first
git ls-files | while read -r file; do
    if [[ -f "$file" ]]; then
        sed -i '' \
            -e 's/AI Assist/AI Agent/g' \
            -e 's/Ai assist/Ai agent/g' \
            -e 's/AiAssist/AiAgent/g' \
            -e 's/aiAssist/aiAgent/g' \
            -e 's/aiassist/aiagent/g' \
            -e 's/ai-assist/ai-agent/g' \
            -e 's/ai_assist/ai_agent/g' \
            "$file"
    fi
done

# Process files and directories from deepest to shallowest
find . \( -type f -o -type d \) | grep -E "(AiAssist|aiassist|ai-assist|ai_assist)" | sort -r | while read -r path; do
    if [[ -e "$path" ]]; then
        newpath=$(echo "$path" | sed \
            -e 's/AiAssist/AiAgent/g' \
            -e 's/aiassist/aiagent/g' \
            -e 's/ai-assist/ai-agent/g' \
            -e 's/ai_assist/ai_agent/g' \
            -e 's/ckeditor_ai_assist/ckeditor_ai_agent/g')
        if [[ "$path" != "$newpath" ]]; then
            git mv "$path" "$newpath" || mv "$path" "$newpath"
        fi
    fi
done
