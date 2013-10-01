<?php 
	add_filter( 'gettext', 'plugin_translation', 20, 3 );
	function plugin_translation( $translated_text, $text, $domain ) {
	    //echo "<pre>" . $text . "</pre>";
	    if ( $domain == 'casasync') {

	        switch ( $text ) {
	        	case 'Buy':$translated_text = 'Kaufen';break;
	        	case 'Rent':$translated_text = 'Mieten';break;

	        	case 'First name':$translated_text = 'Vorname';break;
	            case 'Last name':$translated_text = 'Nachname';break;
	            case 'Email':$translated_text = 'E-Mail';break;
	            case 'Salutation':$translated_text = 'Anrede';break;
	            case 'Title':$translated_text = 'Titel';break;
	            case 'Phone':$translated_text = 'Telefon';break;
	            case 'Company':$translated_text = 'Firma';break;
	            case 'Street':$translated_text = 'Strasse';break;
	            case 'ZIP':$translated_text = 'PLZ';break;
	            case 'City':$translated_text = 'Stadt';break;
	            case 'Locality':$translated_text = 'Ort';break;
	            case 'Kanton':$translated_text = 'Kanton';break;
	            case 'Subject':$translated_text = 'Betreff';break;
	            case 'Message':$translated_text = 'Nachricht';break;
	            case 'Recipient':$translated_text = 'Rezipient';break;
	            case 'Required':$translated_text = 'Erforderlich';break;
	            case 'Please consider the following errors and try sending it again':$translated_text = 'Bitte beachten Sie folgene Fehler und probieren Sie es erneut';break;
	            case '&larr; Page back':$translated_text = '&larr; Seite zurück';break;
	            case 'Page forward &rarr;':$translated_text = 'nächste Seite &rarr;';break;

	            case 'Switzerland': $translated_text = 'Schweiz';break;
	            case 'France': $translated_text = 'Frankreich';break;
	            case 'monthly': $translated_text = 'monatlich';break;
	            case 'weekly': $translated_text = 'wöchentlich';break;
	            case 'daily': $translated_text = 'täglich';break;
	            case 'yearly': $translated_text = 'jährlich';break;
	            case 'hourly': $translated_text = 'stündlich';break;
	            case 'month': $translated_text = 'Monat';break;
	            case 'week': $translated_text = 'Woche';break;
	            case 'day': $translated_text = 'Tag';break;
	            case 'year': $translated_text = 'Jahr';break;
	            case 'hour': $translated_text = 'Stunde';break;
	            case 'per month': $translated_text = 'pro Monat';break;
	            case 'per week': $translated_text = 'pro Woche';break;
	            case 'per day': $translated_text = 'pro Tag';break;
	            case 'per year': $translated_text = 'pro Jahr';break;
	            case 'per hour': $translated_text = 'pro Stunde';break;
	            case 'Base data': $translated_text = 'Grunddaten';break;
	            case 'Specifications': $translated_text = 'Datenblatt';break;
	            case 'Plans & Documents': $translated_text = 'Pläne & Dokumente';break;
	            case 'Address': $translated_text = 'Adresse';break;
	            case 'Rooms:': $translated_text = 'Zimmer:';break;
	            case 'Rooms': $translated_text = 'Zimmer';break;
	            case 'Living space:': $translated_text = 'Wohnfläche:';break;
	            case 'Living space': $translated_text = 'Wohnfläche';break;
	            case 'Floor:': $translated_text = 'Etage:';break;
	            case 'Floor': $translated_text = 'Etage';break;
	            case 'Rent price:': $translated_text = 'Mietpreis:';break;
	            case 'Rent price': $translated_text = 'Mietpreis';break;
	            case 'Sales price:': $translated_text = 'Kaufpreis:';break;
	            case 'Rent price': $translated_text = 'Mietpreis';break;
	            case 'Sales price': $translated_text = 'Kaufpreis';break;
	            case 'Additional costs': $translated_text = 'Nebenkosten';break;
	            case 'Object ID': $translated_text = 'Objekt-ID';break;
	            case 'Floor(s)': $translated_text = 'Stockwerk(e)';break;
	            case 'Features': $translated_text = 'Eigenschaften';break;


		        case 'Email':   				$translated_text =  'E-Mail';break;
		        case 'Mobile':   				$translated_text =  'Mobile';break;
		        case 'Phone direct':   			$translated_text =  'Telefon direkt';break;
		        case 'Phone':   				$translated_text =  'Telefon';break;
		        case 'Fax':   					$translated_text =  'Fax';break;

		        case 'Offer':   				$translated_text =  'Angebot';break;
		        case 'Property':   				$translated_text =  'Objekt';break;
		        case 'Surroundings':   			$translated_text =  'Umfeld';break;
		        case 'Distances:':   			$translated_text =  'Distanzen:';break;
		        case 'Plans':   				$translated_text =  'Pläne';break;
		        case 'Documents':   			$translated_text =  'Dokumente';break;


		        case 'Public transportation':   $translated_text =  'Öffentlicher Verkehr';break;
		        case 'Shopping':               	$translated_text =  'Einkaufen';break;
		        case 'Kindergarten':       		$translated_text =  'Kindergarten';break;
		        case 'Rail connection':         $translated_text =  'Bahnanschluss';break;
		        case 'Motorway':        	 	$translated_text =  'Autobahnanschluss';break;
		        case 'Primary school':          $translated_text =  'Primarschule';break;
		        case 'Secondary school':        $translated_text =  'Oberstufe';break;

		        case 'Living space':   			$translated_text = 'Wohnfläche';break;
        		case 'Property space': 			$translated_text = 'Grundstückfläche';break;
        		case 'Year of renovation':   	$translated_text = 'Renovationsjahr';break;
        		case 'Year of construction':    $translated_text = 'Baujahr';break;
        		case 'Number of rooms':  		$translated_text = 'Anzahl Zimmer';break;
        		case 'Number of floors': 		$translated_text = 'Anzahl Etagen';break;
				
				case 'Directly contact the provider now': 		$translated_text = 'Jetzt Anbieter direkt kontaktieren';break;
				case 'Back to the list': 						$translated_text = 'Zurück zur Übersicht';break;


				case 'Please fill out all the fields':  $translated_text = 'Bitte alle Felder ausfüllen.';break;
				case 'Send':   							$translated_text = 'Senden';break;
				case 'Contact directly':   				$translated_text = 'Direkt kontaktieren';break;

				case 'Provider':   						$translated_text = 'Anbieter';break;
				case 'Contact person':   				$translated_text = 'Kontaktperson';break;
				case 'Share':   						$translated_text = 'Empfehlen';break;
				case 'View lager version':   			$translated_text = 'Grössere Ansicht anzeigen';break;

				case 'Choose category':   				$translated_text = 'Kategorie wählen';break;
				case 'Choose locality':   				$translated_text = 'Ort wählen';break;

				case 'Advanced search':   				$translated_text = 'Erweiterte Suche';break;
				case 'Search':   						$translated_text = 'Suchen';break;
				case 'Details':   						$translated_text = 'Details';break;

				case 'and':   						$translated_text = 'und';break;

				case 'I am interested concerning this property. Please contact me.':   				$translated_text = 'Ich%20interessiere%20mich%20f%C3%BCr%20dieses%20Objekt.%20Bitte%20nehmen%20Sie%20Kontakt%20mit%20mir%20auf.';break;

				




				case 'Wheelchair accessible': $translated_text = 'Rollstuhlzugänglich'; break;
	            case 'Entrances': $translated_text = 'Eingänge';break;

				case 'Child friendly':   		$translated_text =  'Kinderfreundlich';break;
				case 'Garage':   				$translated_text =  'Garage';break;
				case '%d garages':   			$translated_text =  '%d Garagen';break;
				case 'Balcony':   				$translated_text =  'Balkon';break;
				case '%d balconies':   			$translated_text =  '%dx Balkone';break;
				case 'ISDN connection':   		$translated_text =  'ISDN Anschluss';break;
            	case 'Vista':   				$translated_text =  'Aussicht';break;
            	case 'Cable TV':   				$translated_text =  'Kabelfernsehen';break;
            	case '% Parking spaces':   		$translated_text =  '%d Parkplätze';break;
            	case 'Parking space':   		$translated_text =  'Parkplatz';break;
            	case 'Pets allowed':   			$translated_text =  'Haustiere erlaubt';break;
            	case '%d pets allowed':   		$translated_text =  '%d Haustiere erlaubt';break;
            	case 'Restrooms':   			$translated_text =  'Toiletten';break;
            	case '%d restrooms':   			$translated_text =  '%d Toiletten';break;
            	case 'Elevator':   				$translated_text =  'Lift / Aufzug';break;
            	case '%d elevators':   			$translated_text =  '%d Lifte / Aufzüge';break;

		        case 'Water Supply':   			$translated_text =  'Wasseranschluss';break;
		        case 'Power Supply':   			$translated_text =  'Stromanschluss';break;
		        case 'New building':   			$translated_text =  'Neubau';break;
		        case 'Fireplace':   			$translated_text =  'Cheminée';break;
		        case 'wheelchair accessible':   $translated_text =  'Rollstuhlgängig';break;
		        case 'Ramp':   					$translated_text =  'Anfahrrampe';break;
		        case 'lifting platform':   		$translated_text =  'Hebebühne';break;
		        case 'Railway terminal':   		$translated_text =  'Bahnanschluss';break;
		        case 'Sewage supply':   		$translated_text =  'Abwasseranschluss';break;
		        case 'Gas supply':   			$translated_text =  'Gasanschluss';break;
		        case 'Corner house':   			$translated_text =  'Eckhaus';break;
		        case 'Middle house':   			$translated_text =  'Mittelhaus';break;
		        case 'Gardenhouse':   			$translated_text =  'Gartenhaus';break;
		        case 'Raised ground floor':  	$translated_text =  'Hochparterre';break;
		        case 'Old building':   			$translated_text =  'Altbau';break;
		        case 'Under roof':   			$translated_text =  'Gedeckt';break;
		        case 'Swimmingpool':   			$translated_text =  'Swimmingpool';break;
		        case 'Minergie general':   		$translated_text =  'Minergiebauweise';break;
		        case 'Minergie certified':   	$translated_text =  'Minergie zertifiziert';break;
		        case 'Under building laws':   	$translated_text =  'Im Baurecht';break;
		        case 'Building land connected': $translated_text =  'Bauland erschlossen';break;
		        case 'Flat sharing community':  $translated_text =  'In Wohngemeinschaft';break;



				case 'Agriculture':			$translated_text =  'Landwirtschaft';break;
		        case 'Apartment':			$translated_text =  'Wohnung';break;
		        case 'Gastronomy':			$translated_text =  'Gastronomie';break;
		        case 'House':				$translated_text =  'Haus';break;
		        case 'Industrial Objects':	$translated_text =  'Gewerbe/Industrie';break;
		        case 'Industrial':			$translated_text =  'Gewerbe/Industrie';break;
		        case 'Parking space':		$translated_text =  'Parkplatz';break;
		        case 'Plot':				$translated_text =  'Grundstück';break;
		        case 'Secondary rooms':		$translated_text =  'Wohnnebenräume';break;
		        case 'Garden':				$translated_text =  'Garten';break;
		        case 'Commercial':			$translated_text =  'Büro';break;

		        case 'Description':				$translated_text =  'Beschreibung';break;
		        case 'Reference':				$translated_text =  'Referenz';break;
		        case 'Distances':				$translated_text =  'Distanzen';break;

		        case 'Available':				$translated_text =  'Verfügbar';break;
		        case 'Reserved':				$translated_text =  'Reserviert';break;
		        case 'Planned':					$translated_text =  'In Planung';break;
		        case 'Under construction':		$translated_text =  'Im Bau';break;

			
	        }

	    }

	    return $translated_text;
	}