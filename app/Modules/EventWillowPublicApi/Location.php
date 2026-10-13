<?php
namespace App\Modules\EventWillowPublicApi;
function usStates(): array {
    return ['AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','DC'=>'District of Columbia','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming','PR'=>'Puerto Rico','GU'=>'Guam','VI'=>'U.S. Virgin Islands','AS'=>'American Samoa','MP'=>'Northern Mariana Islands'];
}
function stateName(string $state, string $country, string $address = ''): string {
    $state = trim($state);
    if (strtoupper($country) !== 'US') { return $state; }
    foreach (usStates() as $code=>$name) {
        if (strcasecmp($state,$code)===0 || strcasecmp($state,$name)===0) { return $name; }
    }
    // Only infer an absent state from an explicit state name or state+ZIP in the stored address.
    if ($state === '' && $address !== '') {
        foreach (usStates() as $code=>$name) {
            if (preg_match('/(?:^|[,\s])' . preg_quote($name,'/') . '(?:[,\s]|$)/i',$address)
                || preg_match('/(?:^|[,\s])' . $code . '\s+\d{5}(?:-\d{4})?(?:\D|$)/i',$address)) { return $name; }
        }
    }
    return $state;
}
function countryName(string $country): string {
    if (class_exists('Locale')) { return \Locale::getDisplayRegion('und_' . $country, 'en') ?: $country; }
    return ['US'=>'United States','CA'=>'Canada','GB'=>'United Kingdom','AU'=>'Australia','NZ'=>'New Zealand','IE'=>'Ireland','DE'=>'Germany','FR'=>'France','ES'=>'Spain','IT'=>'Italy','IN'=>'India','ZA'=>'South Africa'][$country] ?? $country;
}
function addressLocation(string $address, string $country): array {
    $empty = ['city'=>'', 'state'=>'', 'postal'=>''];
    if (strtoupper($country) !== 'US') return $empty;
    // Only parse an explicit comma-separated city and recognized state suffix, with optional ZIP.
    $states = array_merge(array_keys(usStates()), array_values(usStates()));
    $pattern = implode('|', array_map(fn ($state) => preg_quote($state, '/'), $states));
    if (!preg_match('/,\s*([^,\r\n]+),\s*('.$pattern.')(?:\s+(\d{5}(?:-\d{4})?))?\s*(?:,\s*(?:US|USA|United States))?\s*$/iD', trim($address), $match)) return $empty;
    return ['city'=>trim($match[1]), 'state'=>stateName($match[2], 'US'), 'postal'=>$match[3]??''];
}
