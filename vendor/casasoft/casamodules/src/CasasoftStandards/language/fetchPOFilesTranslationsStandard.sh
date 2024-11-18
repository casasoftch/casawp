printf "Fetching translations for DE backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="de-ch" \
     -d type="po" \
     | jq -j '.result.url' \
     | xargs curl > de.po

printf "Fetching translations for FR backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="fr-ch" \
     -d type="po" \
     | jq -j '.result.url' \
     | xargs curl > fr.po

printf "Fetching translations for IT backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="it-ch" \
     -d type="po" \
     | jq -j '.result.url' \
     | xargs curl > it.po

printf "Fetching translations for EN backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="en" \
     -d type="po" \
     | jq -j '.result.url' \
     | xargs curl > en.po

printf "Fetching translations for ES backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="es" \
     -d type="po" \
     | jq -j '.result.url' \
     | xargs curl > es.po