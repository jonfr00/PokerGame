<?php
session_start();
// Initialize default session variables if not set
if (!isset($_SESSION['score'])) {
    $_SESSION['score'] = 105; // Start with 105 to ensure first round starts with 100
}

if (!isset($_SESSION['hand'])) {
    $_SESSION['hand'] = [];
}

if (!isset($_SESSION['deck'])|| !validateDeck($_SESSION['deck']) ) {
	$_SESSION['deck'] = initializeDeck();
	    if (!validateDeck($_SESSION['deck'])) {
        error_log('Deck validation failed at game start.');
    }
}

if (!isset($_SESSION['isDealt'])) {
    $_SESSION['isDealt'] = false;
}

if (!isset($_SESSION['message'])) {
    $_SESSION['message'] = '';
}

//$_SESSION['score'] = $currentScore; //removed score->-5 Store the score in the session

// Initialize the deck
function initializeDeck($retries = 3)
{
    if ($retries <= 0) {
        error_log('Failed to initialize a valid deck after multiple attempts.');
        throw new Exception('Unable to initialize a valid deck.');
    }
    $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
    $values = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
        '7' => 7, '8' => 8, '9' => 9, '10' => 10,
        'jack' => 11, 'queen' => 12, 'king' => 13, 'ace' => 14
    ];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($values as $name => $value) {
            $deck[] = ['suit' => $suit, 'name' => $name, 'value' => $value];
	}
    }
    shuffle($deck);
    // Validate the initialized deck
    if (!validateDeck($deck)) {
	error_log('Deck initialization failed validation. Reinitializing...');
    	return initializeDeck($retries - 1); // Recursive call to reinitialize if validation fails
    }
    return $deck;
}

function validateDeck($deck)
{
    $validSuits = ['hearts', 'diamonds', 'clubs', 'spades'];
    $validValues = [
        '2', '3', '4', '5', '6', '7', '8', '9', '10',
        'jack', 'queen', 'king', 'ace'
    ];

    $uniqueCards = [];
    foreach ($deck as $card) {
        // Check if card has valid attributes
        if (
            empty($card['suit']) ||
            empty($card['name']) ||
            !in_array($card['suit'], $validSuits) ||
            !in_array($card['name'], $validValues)
        ) {
            error_log('Invalid card detected: ' . print_r($card, true));
            return false;
        }

        // Check for duplicates
        $cardKey = $card['suit'] . '-' . $card['name'];
        if (isset($uniqueCards[$cardKey])) {
            error_log('Duplicate card detected: ' . $cardKey);
            return false;
        }
        $uniqueCards[$cardKey] = true;
    }

    // Validate card count
    if (count($deck) !== 52) {
        error_log('Deck validation failed: Incorrect card count (' . count($deck) . ')');
        return false;
    }

    return true;
}

// Deal a hand of 5 cards
function dealHand(&$deck)
{
	if (count($deck) < 5) {
	error_log('Deck too small, reinitializing...');//***ERROR LOG this line
        $deck = array_merge($deck, initializeDeck());
	shuffle($deck);
	}
    // Validate the deck before dealing
    if (!validateDeck($deck)) {
        error_log('Deck validation failed before dealing. Reinitializing...');
        $deck = initializeDeck();
    }
//***ERROR LOG
    $hand = array_splice($deck, 0, 5);// Deal 5 cards
    foreach ($hand as $card) {
        if (empty($card['name']) || empty($card['suit'])) {
            error_log('Invalid card dealt: ' . print_r($card, true));
        }
    }
    return $hand;
}

// Evaluate the hand
function evaluateHand($hand)
{
    $valuesCount = array_count_values(array_column($hand, 'value'));
    $suitsCount = array_count_values(array_column($hand, 'suit'));

    $isFlush = max($suitsCount) === 5;
    $sortedValues = array_keys($valuesCount);
    sort($sortedValues);
    $isStraight = count($sortedValues) === 5 && ($sortedValues[4] - $sortedValues[0] === 4);

    $isRoyalFlush = $isFlush && $isStraight && $sortedValues[0] === 10;
    $isStraightFlush = $isFlush && $isStraight && !$isRoyalFlush;
    $fourOfAKind = in_array(4, $valuesCount);
    $threeOfAKind = in_array(3, $valuesCount);
    $pairs = count(array_filter($valuesCount, fn($count) => $count === 2));

    $isFullHouse = $threeOfAKind && $pairs === 1;
    $jacksOrBetter = count(array_filter($valuesCount, fn($count, $value) => $count === 2 && $value >= 11, ARRAY_FILTER_USE_BOTH)) > 0;

    if ($isRoyalFlush) return ['win' => 250, 'message' => 'Royal Flush! (1250 points)'];
    if ($isStraightFlush) return ['win' => 50, 'message' => 'Straight Flush! (250 points)'];
    if ($fourOfAKind) return ['win' => 25, 'message' => 'Four of a Kind! (125 points)'];
    if ($isFullHouse) return ['win' => 9, 'message' => 'Full House! (45 points)'];
    if ($isFlush) return ['win' => 6, 'message' => 'Flush! (30 points)'];
    if ($isStraight) return ['win' => 4, 'message' => 'Straight! (20 points)'];
    if ($threeOfAKind) return ['win' => 3, 'message' => 'Three of a Kind! (15 points)'];
    if ($pairs === 2) return ['win' => 2, 'message' => 'Two Pair! (10 points)'];
    if ($jacksOrBetter) return ['win' => 1, 'message' => 'Jacks or Better! (5 points)'];

    return ['win' => 0, 'message' => 'No Win.'];
}

// Automatically deal a hand on the first load
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['hand'])) {
    $_SESSION['hand'] = dealHand($_SESSION['deck']);
//**** ERROR LOGGING
foreach ($_SESSION['hand'] as $card) {
    if (empty($card['name']) || empty($card['suit'])) {
        error_log('Invalid card in session hand: ' . print_r($card, true));
    }
}
    $_SESSION['score'] -= 5;
    $_SESSION['message'] = '';
    $_SESSION['isDealt'] = false;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['play'])) {
        $_SESSION['score'] -= 5; // Deduct 5 points for playing again
        $_SESSION['hand'] = dealHand($_SESSION['deck']); // Deal a new hand
//***ERROR LOGGING
foreach ($_SESSION['hand'] as $card) {
    if (empty($card['name']) || empty($card['suit'])) {
        error_log('Invalid card in session hand: ' . print_r($card, true));
    }
}
	$_SESSION['message'] = '';
        $_SESSION['isDealt'] = false; // Reset isDealt for next round
    }

    if (isset($_POST['deal'])) {
        $heldCards = array_map(fn($index) => isset($_POST['hold'][$index]), range(0, 4));
	$newHand = [];
	$heldCardKeys = [];
        foreach ($_SESSION['hand'] as $index => $card) {
            if ($heldCards[$index]) {
                $newHand[] = $card; // Keep held cards
                $heldCardKeys[] = $card['suit'] . '-' . $card['name'];
            }
	}

	// **Exclude held cards from the deck**
        $_SESSION['deck'] = array_filter($_SESSION['deck'], function ($card) use ($heldCardKeys) {
           $cardKey = $card['suit'] . '-' . $card['name'];
           return !in_array($cardKey, $heldCardKeys);
       });

	// **Shuffle the filtered deck**
        shuffle($_SESSION['deck']);

	// **Replace unheld cards with new ones**
        while (count($newHand) < 5) {
            $newCard = array_pop($_SESSION['deck']);
            $newHand[] = $newCard;
        }

    // **Log duplicate cards if detected**
    $cardCount = [];
    foreach ($newHand as $card) {
        $cardKey = $card['suit'] . '-' . $card['name'];
        if (isset($cardCount[$cardKey])) {
            $cardCount[$cardKey]++;
        } else {
            $cardCount[$cardKey] = 1;
        }
    }

    // ** Log duplicates
    foreach ($cardCount as $cardKey => $count) {
        if ($count > 1) {
            error_log("Duplicate card detected: $cardKey appears $count times in the hand.");
        }
    }


	$_SESSION['hand'] = $newHand;
	$result = evaluateHand($_SESSION['hand']);
	$_SESSION['score'] += $result['win'] * 5; // Add winnings to score
	//$_SESSION['score'] = $currentScore; //removed score->-5 Store the score in the session
        $_SESSION['message'] = $result['message'];
	$_SESSION['isDealt'] = true;

	    // **Log updated session state for debugging**
    //error_log('Updated deck size: ' . count($_SESSION['deck']));
    //error_log('Updated hand: ' . print_r($_SESSION['hand'], true));
    }

    if (isset($_POST['new_game'])) {
        // Reset all session variables to start a new game
        $_SESSION['score'] = 105; // Reset score to default
        $_SESSION['hand'] = []; // Clear the hand
        $_SESSION['message'] = ''; // Clear any messages
        $_SESSION['isDealt'] = false; // Reset dealt status
    }

     // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <title>Poker Game</title>
    <style>
	.container { 
	     padding: 10px;
	     margin: 5px;
	}
        /* New game button styling */
        .new-game-btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            text-align: center;
        }

        .new-game-btn:hover {
            background-color: #0056b3;
        }

        /* Play button styling */
        .play-btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            text-align: center;
        }

        .play-btn:hover {
            background-color: #0056b3;
        }

        /* Card Row Styling */
        .card-row {
            display: flex;
            justify-content: center;
            gap: 20px; /* Space between cards */
	    flex-wrap: wrap; /* Allow wrapping on smaller screens */
	}
	.card-table {
	    border-radius: 15px;
	    padding:50px 0px 50px 0px;
	    background-color:/*#35654D*/green;
        }

        /* Individual Card Styling */
        .card {
            position: relative;
            text-align: center;
        }

        /* Card Image Styling */
        .card img {
            width: 150px; /* Default card width */
            height: auto; /* Maintain aspect ratio */
            max-width: 100%; /* Ensure images fit their container */
            transition: width 0.3s ease; /* Smooth resizing effect */
        }

        /* Label for "Hold" Checkboxes */
	/* previous hold checkbox
	    .hold-label {
            position: relative;
            display: block;
            margin-top: 10px; /* Space between the card and the label *//*
            font-size: 16px;
            color: #333;
            cursor: pointer;
            text-align: center;
        }

        .hold-label input[type="checkbox"] {
            margin-left: 5px; /* Space between label text and checkbox *//*
	}*/

	/* Hold area styling */
	.hold-area {
	    position: absolute;
	    bottom: -28px; /* 2 from -20 to -28 Adjust this for spacing below the card */
	    left: 50%;
	    transform: translateX(-50%);
	    width: 55%; /* 1/3 the width of the card 2 from 33 to 55 */
	    height: 25px !important; /*2 up to 25 from 18 */
	    background-color: #ccc; /* Default background */
	    border-radius: 5px;
	    cursor: pointer;
	    transition: background-color 0.3s ease;
	    text-align: center;
	    line-height: 1.1rem ; /* Vertically center the text */
	    font-size: 1.1rem;
	    text-transform: uppercase;
	    font-weight: bold;
	    color: #000;
	    padding-top: 3px;
	    box-sizing: border-box ; 
	    display: block ; 
	}

	/* Completely hide the checkbox */
	.hold-area input[type="checkbox"] {
	    display: none;
	}

	/* Hold text styling */
	  .hold-area label.hold-text {
	      cursor:pointer;
	      margin-top: 3px;
	  }

	/* Style when checkbox is checked */
	.hold-area input[type="checkbox"]:checked + label {
	    background-color: red !important; /* Change to red */
	    color: white; /* Change text color for contrast */
	    box-shadow: 0px 0px 10px rgba(255, 0, 0, 0.6); /* Add glow effect */
	    position: absolute;
	    bottom: 0px; /* 2 from -20 to -28 Adjust this for spacing below the card */
	    left: 50%;
	    transform: translateX(-50%);
	    width: 100%; /* 1/3 the width of the card 2 from 33 to 55 */
	    height: 25px ; /*2 up to 25 from 18 */
	    background-color: #ccc; /* Default background */
	    border-radius: 5px;
	    cursor: pointer;
	    transition: background-color 0.3s ease;
	    text-align: center;
	    line-height: 1.1rem ; /* Vertically center the text */
	    font-size: 1.1rem;
	    font-weight: bold;
	    text-transform: uppercase;
	    padding-top: 3px;
	    box-sizing: border-box ;
	    display: block ;
	}

        /* Responsive Resizing */
        @media (max-width: 1024px) {
            .card img {
                width: 120px; /* Shrink card width for medium screens */
	    }

            .hold-area /*label.hold-text*/ {
	        font-size: 1rem; /* 1 Slightly reduce text size */
	        line-height: 1rem;
	        font-weight: bold;
	        padding-top: 3px ;
            }

	    .hold-area input[type="checkbox"]:checked + label {
	        font-size: 1rem; /* 1 Slightly reduce text size */
	        line-height: 1rem;
	        font-weight: bold;
	        padding-bottom: 0px ;
	    }
        }

        @media (max-width: 768px) {
            .card img {
                width: 100px; /* Shrink card width further for small screens */
	    }
	    .hold-area  label.hold-text {
	    font-size: .9rem; /* 1 Slightly reduce text size */
            line-height: .9rem;
	    font-weight: bold;
            padding-top: 3px; /*1  Adjust padding */
	    }
	    .hold-area input[type="checkbox"]:checked + label {
	    font-size: .9rem; /* 1 Slightly reduce text size */
	    line-height: .9rem;
	    padding-top: 4px;
             }
        }

        @media (max-width: 480px) {
            .card img {
                width: 60px; /* Further reduce size for very small screens */
	    }
         
        /* Hold text styling */
        .hold-area label.hold-text {
	    pointer-events: all; /* Ensure the text is clickable */
	    color:black ; /* Smooth transition for color change */
	    background-color: transparent; /* Make sure the background is transparent initially */
	    transition: background-color 0.3s ease;
    	    border-radius: 3px;
    	    cursor: pointer;
    	    box-sizing: border-box !important;
    	    font-size: .6rem; /* 1 Slightly reduce text size */
    	    line-height: .6rem;
    	    font-weight:bold;
    	    padding-top: 0px; /*1  Adjust padding */
	}
	.hold-area input[type="checkbox"]:checked + label {
            font-size: .6rem; /* 1 Slightly reduce text size */
	    line-height: .6rem;
	    font-weight: bold;
            padding-top: 8px  ;
             }
        }
        /* Table Styling */
        table {
            width: 60%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-left: 20px;
        }

        table,
        th,
        td {
            border: 1px solid black;
        }

        th,
        td {
            padding: 8px;
            text-align: center;
	}
    </style>
</head>
<body>
<div class = 'container'>
<h1>Poker Game</h1>
<p>Score: <?php echo $_SESSION['score']; ?></p>
<form method="post" >
  <div class="card-table">
    <?php if (!empty($_SESSION['hand']) && is_array($_SESSION['hand'])): ?>
<!--****ERROR LOGGING moved down--><!--
    //<?php
    //if (empty($card['name']) || empty($card['suit'])) {
    //    error_log('Invalid card before rendering: ' . print_r($card, true));
    //}
    //?> -->

   <div class="card-row">
            <?php foreach ($_SESSION['hand'] as $index => $card): ?>
    <?php
    if (empty($card['name']) || empty($card['suit'])) {
        error_log('Invalid card before rendering: ' . print_r($card, true));
        continue; // Skip invalid cards
    }
    ?>

		<div class="card">
	           <!-- path to card image folder -->
		   <img src="/folderPath/cards/<?php echo $card['name'] . '_of_' . $card['suit']; ?>.png"
                         alt="<?php echo $card['name'] . ' of ' . $card['suit']; ?>">
		   <div class="hold-area">
                      <input type="checkbox" name="hold[<?php echo $index; ?>]" id="hold-<?php echo $index; ?>"
			  <?php echo isset($_POST['hold'][$index]) ? 'checked' : ''; ?>
			onclick="toggleLabel(this)">
		      <label for="hold-<?php echo $index; ?>" class="hold-text"><!--Hold-->
			<?php echo isset($_POST['hold'][$index]) ? 'HELD' : 'Hold'; ?></label>
                  </div>
                </div>
            <?php endforeach; ?>
        </div>
  </div><br />
        <?php if ($_SESSION['message']): ?>
   	    <p><?php echo $_SESSION['message']; ?></p>
          <?php else: ?>
            <p>     <br /> </p>
       <?php endif; ?>
    <?php endif; ?>

    <?php if ($_SESSION['score'] > 0): ?>
        <button class="play-btn" type="submit" name="<?php echo $_SESSION['isDealt'] ? 'play' : 'deal'; ?>">
           <?php echo $_SESSION['isDealt'] ? 'Play Again' : 'Deal'; ?>
        </button>
    <?php else: ?>
	<p>Game Over! Your score is 0.</p>
        <button class="new-game-btn" type="submit" name="new_game">New Game</button>
    <?php endif; ?>
</form>

<h2>Winning Hands and Points </h2>
<table>
    <tr>
        <th>Hand</th>
        <th>Points</th>
        <th>Hand</th>
        <th>Points</th>
    </tr>
    <tr>
        <td>Royal Flush</td>
        <td>1250 points&nbsp&nbsp</td>
        <td>Flush</td>
        <td>30 points</td>
    </tr>
    <tr>
        <td>Straight Flush</td>
        <td>250 points</td>
        <td>Straight</td>
        <td>20 points</td>
    </tr>
    <tr>
        <td>Four of a Kind&nbsp</td>
        <td>125 points</td>
        <td>Three of a Kind&nbsp</td>
        <td>15 points</td>
    </tr>
    <tr>
        <td>Full House</td>
        <td>50 points</td>
        <td>Two Pair</td>
        <td>10 points</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
        <td>Jacks or Better</td>
        <td>5 points</td>
    </tr>
   
</table>

<div class="container mx-auto mt-8 px-4 text-center">
        <button id="openSaveModal" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Save High Score
        </button>
        <button id="openShowModal" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded ml-4">
            Show High Scores
        </button>
    </div>

    <!-- Save Modal -->
    <div id="saveModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-1/3">
            <h2 class="text-2xl font-bold mb-4">Save Your High Score</h2>
            <form id="saveScoreForm">
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">Your Name:</label>
                    <input type="text" id="name" name="name" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <input type="hidden" id="score" name="score" value="0">
                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Save Score
                </button>
                <button type="button" id="closeSaveModal" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded ml-4">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Show Modal -->
    <div id="showModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-2/3">
            <h2 class="text-2xl font-bold mb-4">Top High Scores</h2>
            <table class="table-auto w-full text-left">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="px-4 py-2">Rank</th>
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Score</th>
                        <th class="px-4 py-2">Date</th>
                    </tr>
                </thead>
                <tbody id="highScoreTable"></tbody>
            </table>
            <button type="button" id="closeShowModal" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mt-4">
                Close
            </button>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    const saveModal = document.getElementById('saveModal');
    const showModal = document.getElementById('showModal');
    const openSaveModal = document.getElementById('openSaveModal');
    const openShowModal = document.getElementById('openShowModal');
    const closeSaveModal = document.getElementById('closeSaveModal');
    const closeShowModal = document.getElementById('closeShowModal');
    const saveScoreForm = document.getElementById('saveScoreForm');
    const highScoreTable = document.getElementById('highScoreTable');

    // Open/Close Modals
    openSaveModal.addEventListener('click', () => saveModal.classList.remove('hidden'));
    openShowModal.addEventListener('click', () => {
        showModal.classList.remove('hidden');
        fetch('pokerscore.php?action=display')
            .then(res => res.json())
            .then(data => {
                highScoreTable.innerHTML = data.data.map((score, index) => `
                    <tr>
                        <td class="px-4 py-2">${index + 1}</td>
                        <td class="px-4 py-2">${score.name}</td>
                        <td class="px-4 py-2">${score.score}</td>
                        <td class="px-4 py-2">${score.date}</td>
                    </tr>
                `).join('');
            });
    });

    closeSaveModal.addEventListener('click', () => saveModal.classList.add('hidden'));
    closeShowModal.addEventListener('click', () => showModal.classList.add('hidden'));

    // Save Score Form Submission
    saveScoreForm.addEventListener('submit', e => {
        e.preventDefault();

        // Dynamically set the score from the session
        const score = <?php echo $_SESSION['score'] ?? 0; ?>;  // Get the score from the PHP session

        // Set the score value in the hidden input
        document.getElementById('score').value = score;

        const formData = new FormData(saveScoreForm);

        fetch('pokerscore.php', {
            method: 'POST',
            body: formData,
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            saveModal.classList.add('hidden');
        });
    });
    //toggle hold to held input label
    function toggleLabel(checkbox) {
    let label = checkbox.nextElementSibling;
    label.textContent = checkbox.checked ? 'HELD' : 'Hold';
    }
    //Hide Hold input checkbox text affter dealing cards
    document.addEventListener("DOMContentLoaded", function () {
    if (<?php echo $_SESSION['isDealt'] ? 'true' : 'false'; ?>) {
        document.querySelectorAll(".hold-area").forEach(function (holdArea) {
            holdArea.style.display = "none";
        });
    }
    });
</script>
</body>
</html>

