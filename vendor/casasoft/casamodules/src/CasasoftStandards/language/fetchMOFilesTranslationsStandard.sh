printf "Fetching translations for DE backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="de-ch" \
     -d type="mo" \
     | jq -j '.result.url' \
     | xargs curl > de.mo

printf "Fetching translations for FR backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="fr-ch" \
     -d type="mo" \
     | jq -j '.result.url' \
     | xargs curl > fr.mo

printf "Fetching translations for IT backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="it-ch" \
     -d type="mo" \
     | jq -j '.result.url' \
     | xargs curl > it.mo

printf "Fetching translations for EN backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="en" \
     -d type="mo" \
     | jq -j '.result.url' \
     | xargs curl > en.mo

printf "Fetching translations for ES backend:${NC}\n";
curl -X POST https://api.poeditor.com/v2/projects/export \
     -d api_token="f360e748f3bd15aa7d0dd369242d3507" \
     -d id="126727" \
     -d language="es" \
     -d type="mo" \
     | jq -j '.result.url' \
     | xargs curl > es.mo