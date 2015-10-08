<?php 
	add_filter( 'gettext', 'plugin_translation', 20, 3 );
	function plugin_translation( $translated_text, $text, $domain ) {
	    //echo "<pre>" . $text . "</pre>";
	    if ( $domain == 'casawp') {

	        switch ( $text ) {
	        	case 'Buy':$translated_text = 'Acquisto';break;
	        	case 'Rent':$translated_text = 'Affitto';break;

	        	case 'First name':$translated_text = 'Nome';break;
	            case 'Last name':$translated_text = 'Cognome';break;
	            case 'Email':$translated_text = 'E-Mail';break;
	            case 'Salutation':$translated_text = 'Saluto';break;
	            case 'Title':$translated_text = 'Vocazione';break;
	            case 'Company':$translated_text = 'Ditta';break;
	            case 'Street':$translated_text = 'Strada';break;
	            case 'ZIP':$translated_text = 'NPA';break;
	            case 'City':$translated_text = 'Luogo';break;
	            case 'Locality':$translated_text = 'Località';break;
	            case 'Kanton':$translated_text = 'Canton';break;
	            case 'Subject':$translated_text = 'Concerne';break;
	            case 'Message':$translated_text = 'Messaggio';break;
	            case 'Recipient':$translated_text = 'Destinatario';break;
	            case 'Required':$translated_text = 'Richiesto';break; //***
	            case 'Please consider the following errors and try sending it again':$translated_text = 'Si prega di considerare i seguenti errori e provare a inviare di nuovo';break;
	            case '&larr; Page back':$translated_text = '&larr; pagina indietro';break;
	            case 'Page forward &rarr;':$translated_text = 'pagina avanti &rarr;***';break;

	            case 'Switzerland': $translated_text = 'Svizzera';break;
	            case 'France': $translated_text = 'Francia';break;
	            case 'monthly': $translated_text = 'mensile';break;
	            case 'weekly': $translated_text = 'settimanale';break;
	            case 'daily': $translated_text = 'quotidiano';break;
	            case 'yearly': $translated_text = 'annuale';break;
	            case 'hourly': $translated_text = 'ogni ora';break;
	            case 'month': $translated_text = 'mese';break;
	            case 'week': $translated_text = 'settimana';break;
	            case 'day': $translated_text = 'giorno';break;
	            case 'year': $translated_text = 'anno';break;
	            case 'hour': $translated_text = 'ora';break;
	            case 'per month': $translated_text = 'al mese';break;
	            case 'per week': $translated_text = 'a settimana';break;
	            case 'per day': $translated_text = 'al giorno';break;
	            case 'per year': $translated_text = 'all\'anno';break;
	            case 'per hour': $translated_text = 'all\'ora';break;
	            case 'Base data': $translated_text = 'Dati di base';break;
	            case 'Specifications': $translated_text = 'specificazioni';break;
	            case 'Plans & Documents': $translated_text = 'Piani e Documenti';break;
	            case 'Address': $translated_text = 'Indirizzo';break;
	            case 'Rooms:': $translated_text = 'Camere:';break;
	            case 'Rooms': $translated_text = 'Camere';break;
	            case 'Living space:': $translated_text = 'Superficie abitabile:';break;
	            case 'Living space': $translated_text = 'Superficie abitabile';break;
	            case 'Floor:': $translated_text = 'Piano:';break;
	            case 'Floor': $translated_text = 'Piano';break;
	            case 'Rent price:': $translated_text = 'Affitto:';break;
	            case 'Rent price': $translated_text = 'Affitto';break;
	            case 'Sales price:': $translated_text = 'Acquisto:';break;
	            case 'Sales price': $translated_text = 'Acquisto';break;
	            case 'Additional costs': $translated_text = 'Costi aggiuntivi';break;
	            case 'Object ID': $translated_text = 'Oggetto ID';break;
	            case 'Floor(s)': $translated_text = 'Piano';break;
	            case 'Features': $translated_text = 'Proprietà';break;

		        case 'Email':   				$translated_text =  'E-Mail';break;
		        case 'Mobile':   				$translated_text =  'Cellulare';break;
		        case 'Phone direct':   			$translated_text =  'Telefono diretta';break;
		        case 'Phone':   				$translated_text =  'Telefono';break;
		        case 'Fax':   					$translated_text =  'Telefax';break;

		        case 'Offer':   				$translated_text =  'Offrire';break;
		        case 'Property':   				$translated_text =  'Oggetto';break;
		        case 'Surroundings':   			$translated_text =  'Surroundings***';break;
		        case 'Distances:':   			$translated_text =  'Distanze:';break;
		        case 'Plans':   				$translated_text =  'Piani';break;
		        case 'Documents':   			$translated_text =  'Documenti';break;

		        case 'Living space':   			$translated_text = 'Superficie abitabile';break;
        		case 'Property space': 			$translated_text = 'Superficie terreno';break;
        		case 'Year of renovation':   	$translated_text = 'Year of renovation***';break;
        		case 'Year of construction':    $translated_text = 'Anno di costruzione';break;
        		case 'Number of rooms':  		$translated_text = 'Numero di camere';break;
        		case 'Number of floors': 		$translated_text = 'Numero di piani';break;
				
				case 'Directly contact the provider now': 		$translated_text = 'Direttamente contattare il fornitore ora';break;
				case 'Back to the list': 						$translated_text = 'Torna alla lista';break;

				case 'Please fill out all the fields':  $translated_text = 'Si prega di compilare tutti i campi';break;
				case 'Send':   							$translated_text = 'inviare';break;
				case 'Contact directly':   				$translated_text = 'Contattateci direttamente';break;

				case 'Provider':   						$translated_text = 'Provider';break;
				case 'Contact person':   				$translated_text = 'Persona di contatto';break;
				case 'Share':   						$translated_text = 'Dividere';break;
				case 'View lager version':   			$translated_text = 'Visualizza versione più grande';break;

				case 'Choose category':   				$translated_text = 'Scegliere categorie';break;
				case 'Choose locality':   				$translated_text = 'Scegliere località';break;

				case 'Advanced search':   				$translated_text = 'Ricerca avanzata';break;
				case 'Search':   						$translated_text = 'Cercare';break;
				case 'Details':   						$translated_text = 'Informazioni';break;

				case 'and':   						$translated_text = 'e';break;

				case 'I am interested concerning this property. Please contact me.':   				$translated_text = 'Mi interesso riguardo a questo immobile. Si prega di contattare me.';break;

				



				case 'Wheelchair accessible': $translated_text = 'Accessibile ai disabili'; break;
	            case 'Entrances': $translated_text = 'Eingänge';break;

				case 'Child friendly':   		$translated_text =  'Adatto ai bambini';break;
				case 'Garage':   				$translated_text =  'Garage';break;
				case '%d garages':   			$translated_text =  '%d Garage';break;
				case 'Balcony':   				$translated_text =  'Balcone';break;
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
            	case 'Elevator':   				$translated_text =  'Ascensore';break;
            	case '%d elevators':   			$translated_text =  '%d Ascensore';break;

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

		        case 'Public transportation':   $translated_text =  'Trasporto pubblico';break;
		        case 'Shopping':               	$translated_text =  'Einkaufen';break;
		        case 'Kindergarten':       		$translated_text =  'Asilo';break;
		        case 'Rail connection':         $translated_text =  'Collegamento ferroviario';break;
		        case 'Primary school':          $translated_text =  'scuola elementare';break;
		        case 'Secondary school':        $translated_text =  'scuola secondaria';break;

				
				case 'Agriculture':				$translated_text =  'Agricola';break;
		        case 'Apartment':				$translated_text =  'Appartamenti';break;
		        case 'Gastronomy':				$translated_text =  'Gastronomia';break;
		        case 'House':					$translated_text =  'Case';break;
		        case 'Industrial Objects':		$translated_text =  'Industria/Commercio';break;
		        case 'Parking space':			$translated_text =  'Posteggio esterno';break;
		        case 'Plot':					$translated_text =  'Terreno';break;
		        case 'Secondary rooms':			$translated_text =  'Locali di Servizio';break;
		        case 'Garden':					$translated_text =  'Giardino';break;

		        case 'Available':				$translated_text =  'Disponibile';break;
		        case 'Reserved':				$translated_text =  'riservato';break;
		        case 'Planned':					$translated_text =  'Previsto';break;
		        case 'Under construction':		$translated_text =  'In costruzione';break;

			
	        }

	    }

	    return $translated_text;
	}