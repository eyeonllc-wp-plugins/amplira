#!/bin/bash

# Read the stable tag from readme.txt
stable_tag=$(grep -E '^Stable tag:' readme.txt | awk '{print $NF}' | tr -d '\r')

# Check if the stable tag is not empty
if [ -n "$stable_tag" ]; then
  # Accept commit message as a command line argument
  commit_message=$1

  # Add, commit, and push changes
  git add .
  git commit -m "$commit_message"
 
  # Push to master and check the exit status
  if git push origin master; then
    
    if git rev-parse -q --verify "refs/tags/$stable_tag" >/dev/null; then
      echo "Error: Tag '$stable_tag' already exists. Aborting script."
    else
      git tag $stable_tag
      git push origin $stable_tag

      # Release version
      gh release create $stable_tag --notes "$commit_message"

      echo "Version Released: $stable_tag"
    fi
  else
    echo "Error: Failed to push changes to master branch."
  fi
else
  echo "Error: Stable tag not found in readme.txt."
fi
