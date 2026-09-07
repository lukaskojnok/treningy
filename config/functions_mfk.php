<?php

  // renderStandingsTable(
  //     $db,
  //     '2025/2026',
  //     'u09-m-a',
  //     ['position', 'team', 'matches', 'wins', 'draws', 'losses', 'score', 'points', 'form']
  // );

function renderStandingsTable(
    string $season,
    string $teamSlug,
    array $columns = ['position', 'team', 'matches', 'wins', 'draws', 'losses', 'score', 'points', 'coefficient', 'plus_minus', 'form'],
    $h3show = 0
): void {
  global $db;

    $query = $db->prepare("
        SELECT *
        FROM tables_standings
        WHERE season = :season
        AND team_slug = :team_slug
        AND club_name IS NOT NULL
        AND club_name != ''
        ORDER BY club_position ASC
    ");

    $query->execute([
        'season' => $season,
        'team_slug' => $teamSlug,
    ]);

    $standings = $query->fetchAll(PDO::FETCH_ASSOC);

    $availableColumns = [
        'position' => '#',
        'team' => 'Tím',
        'matches' => 'Z',
        'wins' => 'V',
        'draws' => 'R',
        'losses' => 'P',
        'score' => 'Skóre',
        'points' => 'B',
        'coefficient' => 'K',
        'plus_minus' => '+/-',
        'form' => 'Forma',
    ];

    $columns = array_values(array_filter($columns, function ($column) use ($availableColumns) {
        return isset($availableColumns[$column]);
    }));

    if (empty($standings)) {
        echo '<div class="alert alert-warning">Tabuľka nebola nájdená.</div>';
        return;
    }

    echo '<div class="mfkbox">';
    if ($h3show==1) echo '<h3>' . TEAMS[$teamSlug] . ' <small>| tabuľka</small></h3>';
    echo '<div class="table_responsive"><table class="mfk-standings-table">';

    echo '<thead><tr>';

    foreach ($columns as $column) {
        echo '<th>' . htmlspecialchars($availableColumns[$column]) . '</th>';
    }

    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($standings as $row) {
        echo '<tr ' . ($row['club_name']=="MFK Revúca" ? 'class="red"' : '') . '>';

        foreach ($columns as $column) {
            echo '<td '. ($column=="form" ? 'style="text-align:right !important;"' : '') .'>';

            switch ($column) {
                case 'position':
                    echo (int)$row['club_position'];
                    break;

                case 'team':
                    echo '<div style="display:flex;align-items:center;gap:10px;">';

                    if (!empty($row['club_logo'])) {
                        echo '<img src="/data_club_logos/' . htmlspecialchars($row['club_logo']) . '" alt="" style="width:24px;height:24px;object-fit:contain;">';
                    }

                    if (!empty($row['club_url'])) {
                        // echo '<a href="' . htmlspecialchars($row['club_url']) . '" target="_blank">';
                        echo htmlspecialchars($row['club_name']);
                        // echo '</a>';
                    } else {
                        echo htmlspecialchars($row['club_name']);
                    }

                    echo '</div>';
                    break;

                case 'matches':
                    echo (int)$row['matches'];
                    break;

                case 'wins':
                    echo (int)$row['wins'];
                    break;

                case 'draws':
                    echo (int)$row['draws'];
                    break;

                case 'losses':
                    echo (int)$row['losses'];
                    break;

                case 'score':
                    echo htmlspecialchars((string)$row['score']);
                    break;

                case 'points':
                    echo '<strong>' . (int)$row['points'] . '</strong>';
                    break;

                case 'coefficient':
                    echo htmlspecialchars((string)$row['coefficient']);
                    break;

                case 'plus_minus':
                    echo htmlspecialchars((string)$row['plus_minus']);
                    break;

                case 'form':
                    $form = json_decode($row['form_json'], true);

                    if (!is_array($form)) {
                        $form = [];
                    }

                    echo '<div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center;">';

                    foreach ($form as $match) {
                        $result = strtoupper($match['result'] ?? '');

                        if ($result === '') {
                            continue;
                        }

                        $bg = '#999';

                        if ($result === 'V') {
                            $bg = '#28a745';
                        } elseif ($result === 'R') {
                            $bg = '#ffc107';
                        } elseif ($result === 'P') {
                            $bg = '#dc3545';
                        }

                        $url = $match['detail_url'] ?? '';
                        $tooltip = $match['tooltip_html'] ?? '';

                        echo '<span title="' . htmlspecialchars(strip_tags(html_entity_decode($tooltip))) . '" style="
                            width:24px;
                            height:24px;
                            border-radius:4px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            color:#fff;
                            font-size:12px;
                            font-weight:bold;
                            text-decoration:none;
                            background:' . $bg . ';
                        ">';
                        echo htmlspecialchars($result);
                        echo '</span>';
                    }

                    echo '</div>';
                    break;
            }

            echo '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table></div>';
    echo '</div>';
}






//////////////////////////// ZAPASY






function getMatches(
    string $teamSlug = '',
    string $status = '',
    int $limit = 100,
    string $season = ''
): array {

    global $db;

    $where = [];
    $params = [];

    if ($season !== '') {
        $where[] = "season = :season";
        $params[':season'] = $season;
    }

    if ($teamSlug !== '') {
        $where[] = "team_slug = :team_slug";
        $params[':team_slug'] = $teamSlug;
    }

    if ($status !== '') {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    $where[] = "visibled = :visibled";
    $params[':visibled'] = 1;

    $whereSql = '';

    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    if ($status === 'scheduled') {
        $orderSql = "ORDER BY match_datetime ASC";
    } elseif ($status === 'finished') {
        $orderSql = "ORDER BY match_datetime DESC";
    } else {
        $orderSql = "
            ORDER BY
                CASE
                    WHEN match_datetime >= NOW() THEN 0
                    ELSE 1
                END ASC,

                CASE
                    WHEN match_datetime >= NOW() THEN match_datetime
                END ASC,

                CASE
                    WHEN match_datetime < NOW() THEN match_datetime
                END DESC
        ";
    }

    $query = $db->prepare("
        SELECT *
        FROM matches
        {$whereSql}
        {$orderSql}
        LIMIT :limit
    ");

    foreach ($params as $key => $value) {
        $query->bindValue($key, $value, PDO::PARAM_STR);
    }

    $query->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $query->execute();

    return $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function skDate(string $date): string
{
    $months = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MÁJ',
        6 => 'JÚN',
        7 => 'JÚL',
        8 => 'AUG',
        9 => 'SEP',
        10 => 'OKT',
        11 => 'NOV',
        12 => 'DEC'
    ];

    $time = strtotime($date);

    return date('j.', $time) . ' ' . $months[(int)date('n', $time)] . ' ' . date('Y', $time);
}

function renderMatches(array $matches = [], $hr3show = 0): string
{
    if (!$matches) {
        return '
            <div class="no-matches">
                Žiadne zápasy.
            </div>
        ';
    }

    ob_start();
    ?>

    <div class="matches-list mt-20">
        <?php if ($hr3show==1) { ?>
        <div class="" style="padding:20px">
          <h3>Zápasy</small></h3>
        </div><!--   -->
        <?php } ?>
 
        <?php foreach ($matches as $match): ?>

            <div class="match-row">

                <div class="match-date">
                    <?php
                    if (ADMIN_ACTIVE) {
                      echo "<div style='margin-bottom:10px'><a style='color:black;font-size:12px' href='/admin/moduls_spec/zapasy/zapasy.php#{$match['id']}' target='_blank'>EDITOVAŤ</a></div>";
                    }
                    ?>  

                    <div class="match-day">
                        <?= skDate($match['match_date']) ?>
                    </div>

                    <div class="match-time">
                        <?= date('H:i', strtotime($match['match_time'])) ?>
                    </div>

                    <div class="match-league">
                        <?= htmlspecialchars($match['team_name']) ?>
                    </div>

                </div>

                <div class="match-team home">

                    <?php if (!empty($match['home_team_logo'])): ?>
                        <img src="/data_club_logos/<?= htmlspecialchars($match['home_team_logo']) ?>" alt="">
                    <?php endif; ?>

                    <span><?= htmlspecialchars($match['home_team_name']) ?></span>

                </div>

                <div class="match-center">

                    <?php if ($match['status'] === 'finished'): ?>

                        <span class="score">
                            <?= $match['home_score'] !== null ? htmlspecialchars($match['home_score']) : '-' ?>
                            :
                            <?= $match['away_score'] !== null ? htmlspecialchars($match['away_score']) : '-' ?>
                        </span>

                    <?php else: ?>

                        <span class="vs">- : -</span>

                    <?php endif; ?>

                    <?php if (!empty($match['match_note'])): ?>
                        <div class="match-status">
                            <?= htmlspecialchars($match['match_note']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($match['note'])): ?>
                        <div class="match-status">
                            <?= htmlspecialchars($match['note']) ?>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="match-team away">

                    <?php if (!empty($match['away_team_logo'])): ?>
                        <img src="/data_club_logos/<?= htmlspecialchars($match['away_team_logo']) ?>" alt="">
                    <?php endif; ?>

                    <span><?= htmlspecialchars($match['away_team_name']) ?></span>

                </div>

                <div class="match-stadium">
                    <?= !empty($match['place']) ? htmlspecialchars($match['place']) : 'ŠTADIÓN'; ?>
                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <?php
    return ob_get_clean();
}





//////////////////////////// HRACI





function renderPlayersTable(?string $teamId = null): void {
    global $db;

    $sql = "
        SELECT 
            p.*,
            pd.link,
            pd.title_h1,
            pd.text
        FROM players p
        LEFT JOIN players_data pd 
            ON pd.item_id = p.id
            AND pd.lang = :lang
        WHERE p.active = 1
    ";

    $params = [
        "lang" => LANG
    ];

    if (!empty($teamId)) {
        $sql .= " AND p.teams_ids = :team_id ";
        $params["team_id"] = $teamId;
    }

    $sql .= " ORDER BY p.poradie ASC ";

    $query = $db->prepare($sql);
    $query->execute($params);

    $players = $query->rowCount()
        ? $query->fetchAll(PDO::FETCH_ASSOC)
        : [];
    ?>

    <section class="players-section mt-20">
        <div class="players-table">

            <div class="players-head">
                <div>#</div>
                <div>Hráč</div>
                <div>Tím</div>
                <div>Post</div>
                <div>Vek</div>
            </div>

            <?php if (!empty($players)): ?>

                <?php foreach ($players as $player): ?>

                    <?php
                    $teamKey = $player['teams_ids'] ?? '';
                    $teamName = TEAMS[$teamKey] ?? $teamKey;

                    $postKey = $player['post'] ?? '';
                    $postName = POSTY[$postKey] ?? $postKey;

                    $playerName = $player['title_h1'] ?? '';
                    $birthDate = $player['narodeny'] ?? '';
                 
                 
                    $titleimage_v = "/img/soccer-player.svg";
                    if ( !empty($player["titleimage"]) ) {
                      $titleimage_ = file_filemanager( $player["titleimage"], [] );
                      if ( file_exists($titleimage_["path"]) ) {
                        $titleimage_v = $titleimage_["pathshort"];
                      }
                    }
                 ?>

                    <div class="player-row">

                        <div class="number">
                            <?= !empty($player['poradie']) ? "<small>#</small>" . htmlspecialchars($player['poradie'], ENT_QUOTES, 'UTF-8') : "-"; ?>
                        </div>

                        <div class="player-info">
                            <?php if ( $titleimage_v != "/img/soccer-player.svg" ) { ?>
                              <a href="<?= $titleimage_v; ?>" data-fancybox="hrac_<?php echo $player['id']; ?>">
                            <?php } ?>
                                <img src="<?= $titleimage_v; ?>" alt="<?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8'); ?>" >
                            <?php if ( $titleimage_v != "/img/soccer-player.svg" ) { ?>
                            </a>
                            <?php } ?>
                            
                            <strong>
                                <?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                        </div>

                        <div class="team">
                            <?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8'); ?>
                        </div>

                        <div class="position">
                            <?= htmlspecialchars($postName, ENT_QUOTES, 'UTF-8'); ?>
                        </div>

                        <div class="age">
                            <?php echo getAgeFromDate($birthDate); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="players-empty">
                    Žiadni hráči neboli nájdení.
                </div>

            <?php endif; ?>

        </div>
    </section>

    <?php
}



function getAgeFromDate(?string $date): string {

    if (empty($date)) {
        return "";
    }

    // vyčistí medzery
    $date = trim($date);

    // podpora:
    // 28.02.1986
    // 28.2.1986
    // 28 . 2 . 1986
    $date = preg_replace('/\s+/', '', $date);

    $parts = explode('.', $date);

    if (count($parts) !== 3) {
        return "";
    }

    $day = (int)$parts[0];
    $month = (int)$parts[1];
    $year = (int)$parts[2];

    if (!checkdate($month, $day, $year)) {
        return "";
    }

    try {

        $birthDate = new DateTime("$year-$month-$day");
        $today = new DateTime();

        return (string)$birthDate->diff($today)->y;

    } catch (Exception $e) {

        return "";
    }
}




/////////////////////////////// TRENERI





function vypisTrenerov($selectedTeam) {
  $TRENERI = TRENERI;

  if (empty($selectedTeam) || empty($TRENERI) || !is_array($TRENERI)) {
    return;
  }

  echo '<div class="treneri_list">';

  foreach ($TRENERI as $trener) {

    if (
      empty($trener["timy"]) ||
      !is_array($trener["timy"]) ||
      !isset($trener["timy"][$selectedTeam])
    ) {
      continue;
    }

    $meno = htmlspecialchars($trener["meno"] ?? "", ENT_QUOTES, "UTF-8");

    $phone = htmlspecialchars($trener["phone"] ?? "", ENT_QUOTES, "UTF-8");

    $email = htmlspecialchars($trener["email"] ?? "", ENT_QUOTES, "UTF-8");
    $foto = !empty($trener["photo"]) ? "/data_realizacny_tim/".$trener["photo"] : "/img/user.svg";

    $post = htmlspecialchars($trener["timy"][$selectedTeam], ENT_QUOTES, "UTF-8");

    $phoneHref = preg_replace('/\s+/', '', $phone);

        echo '
        <div class="treneri_item">
        <div class="treneri_avatar">
            <img src="' . $foto . '" alt="' . $meno . '">
        </div>
        <div class="treneri_content">

            <h2>' . $meno . '</h2>

            <span>' . $post . '</span>';

            if (!empty($phoneHref)) {
                echo '
                <p>
                    <i class="fa-solid fa-phone"></i>
                    <a href="tel:' . $phoneHref . '">' . $phone . '</a>
                </p>';
            }

            if (!empty($email)) {
                echo '
                <p>
                    <i class="fa-solid fa-envelope"></i>
                    <a href="mailto:' . $email . '">' . $email . '</a>
                </p>';
            }

        echo '
        </div><!-- treneri_content -->
        </div><!-- treneri_item -->
        ';
  }

  echo '</div><!-- treneri_list -->';
}
?>