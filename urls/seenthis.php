<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2011                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) return;

/*
	Ce jeu d'URLs est une variation de urls/arbo
	qui prend en compte les urls de message
*/

if (!defined('_URLS_ARBO_MIN')) define('_URLS_ARBO_MIN', 0);

define('URLS_SEENTHIS_EXEMPLE', 'message/12 people/login tag/mot-clé');

if (!defined('_MARQUEUR_URL'))
	define('_MARQUEUR_URL', false);

## utiliser des mots anglais
$GLOBALS['url_arbo_types']=array(
	'mot'=>'tag',
	'auteur'=>'people',
);

## delicate composition pour prendre le login a la place du nom dans l'URL
include_spip('public/interfaces');
$GLOBALS['table_titre']['auteurs'] = 'login AS titre, lang';


// http://doc.spip.org/@urls_libres_dist
function urls_seenthis_dist($i, &$entite, $args='', $ancre='') {

	// charger les URLs arbo, qui sont la base de notre systeme d'URLs
	$arbo = charger_fonction('arbo', 'urls');


	if (is_numeric($i)) {
		# #URL_MOT
		if ($entite == "mot") {
			$k = sql_fetsel('m.titre AS titre, g.titre AS type, m.id_groupe AS id_groupe FROM spip_mots AS m LEFT JOIN spip_groupes_mots AS g ON m.id_groupe=g.id_groupe WHERE m.id_mot='.sql_quote($i));
			if (!$k) return '';

			# tag/spip
			if ($k['id_groupe'] == 1)
				$tag = $k['titre'];
			# tag/technology:radiation
			else
				$tag = $k['type'].':'.$k['titre'];
			$g = _DIR_RACINE.$GLOBALS['url_arbo_types']['mot'].'/'
				. urlencode_1738(mb_strtolower($tag,'UTF8'));
		}

		# #URL_ME
		if ($entite == 'me') {
			# s'il y a un parent, c'est #URL_ME{parent}#message$i
			$k = sql_allfetsel('id_me,id_parent', 'spip_me', 'id_me='.$i);
			if (!$k[0]) $g = '';
			if ($k[0]['id_parent'])
				$g = urls_seenthis_dist($k[0]['id_parent'], $entite, $args, 'message'.$i);
			# sinon c'est messages/$i
			else
				$g = 'messages/'.$i;

			// Ajouter les args
			if ($args)
				$g .= ((strpos($g, '?')===false) ? '?' : '&') . $args;
		
			// Ajouter l'ancre
			if ($ancre)
				$g .= "#$ancre";

		}

		# #URL_AUTEUR
		if ($entite == "auteur") {
			$k = sql_fetsel('login FROM spip_auteurs WHERE id_auteur='.sql_quote($i));
			if (!$k) return '';

			# people/login
			$g = _DIR_RACINE.$GLOBALS['url_arbo_types']['auteur'].'/'
				. urlencode_1738(mb_strtolower($k['login'],'UTF8'));

		}
	} else if (TRUE) {

		# la page /people/
		if (preg_match(',/people/?$,', $i)) {
			$g = array(array(), 'people');
		}
		# la page people/xxx/follow/feed => ramener sur people/xxx
		else if (
			preg_match(',^.*(/people/(.*?))((/follow)?/feed)(\?|$),', $i, $r)
		OR
			preg_match(',^.*(/people/(.*)),', $i, $r)
		) {
			if ($f = sql_fetsel('id_auteur', 'spip_auteurs', 'login='.sql_quote($r[2]))) {
				$args['id_auteur'] = $f['id_auteur'];
				$g = array(
					$args
				);

				switch ($r[3]) {
					case '/follow/feed':
						$g[1] = "backend_auteur_follow";
						break;
					case '/feed':
						$g[1] = "backend";
						break;
					case null:
						$g[1] = "auteur";
						break;
					default:
						echo "ERREUR";
				}
			}
		}
		else
		if (preg_match(',/messages/(\d+)$,', $i, $r)) {
			$g = array(
				array('id_me' => $r[1]),
				'message',
				null,
				null
			);
		}
		# la page d'un tag manuel ou opencalais :
		else if (preg_match(',/tag/(([^:]+):(.*)|(.*))$,',
		preg_replace('/[?].*$/', '', $i), $r)) {
			# tag/spip
			if (isset($r[4])) {
				$type = 'Hashtags';
				$titre = urldecode($r[4]);
				$tag = "#$titre";
			} else {
				$type = urldecode($r[2]);
				$titre = urldecode($r[3]);
				$tag = "$type:$titre";
			}
			switch (substr($titre,-1)) {
				# spip$ = seulement le mot 'spip'
				case '$':
					$fond = 'mot_fin';
					$titre = substr($titre,0,-1);
					$tag = substr($tag,0,-1);
					break;
				# spip* = 'spip', 'spip_zone' etc
				case '*':
					$fond = 'mot_flou';
					$titre = substr($titre,0,-1);
					$tag = substr($tag,0,-1);
					break;
				# spip* = 'spip', 'cms' etc (tous les thèmes liés à 'spip')
				default:
					$fond = 'mot';
					break;
			}
			# tag/truc/feed => flux RSS du tag
			if (substr($titre,-5) == '/feed') {
				$titre = substr($titre,0,-5);
				$tag = substr($tag,0,-5);
				$fond = 'backend_mot';
			}
			$args['tag'] = $tag;

			/* old style = id_mot */
			if ($f = sql_fetsel('m.id_mot AS id_mot', 'spip_mots AS m LEFT JOIN spip_groupes_mots AS g ON m.id_groupe=g.id_groupe', 'm.titre='.sql_quote($titre).' AND g.titre='.sql_quote($type))) {
				$args['id_mot'] = $f['id_mot'];

			$g = array(
				$args,
				$fond,
				null,
				null
			);

			} # une fois les vieux urls de mots resorbes, on pourra supprimer ce if()

		}
	}

	// Sinon on se base sur l'url arbo
	if (!isset($g)) {
		$g = $arbo($i, $entite, $args, $ancre);
		
		# si c'est un mot old-style, on redirige vers l'URL new-style
		if (is_array($g) AND $g[1] == 'mot' AND isset($g[0]['id_mot']) ) {
			include_spip('inc/filtres_mini');
			$g[2] = url_absolue(generer_url_entite($g[0]['id_mot'], $g[1]));
		}
	}

	return $g;
}

?>
