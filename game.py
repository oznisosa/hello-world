"""Juego de escape del colegio.

Shavy debe escapar del colegio evitando a los monstruos.
"""
from __future__ import annotations

from dataclasses import dataclass
import random
from typing import Dict, Iterable, List, Tuple

Position = Tuple[int, int]


@dataclass
class Entity:
    """Entidad básica del juego."""

    name: str
    symbol: str
    position: Position

    def move(self, delta: Position) -> Position:
        x, y = self.position
        dx, dy = delta
        return x + dx, y + dy


class School:
    """Representa el colegio como una cuadrícula con obstáculos."""

    width: int = 9
    height: int = 7

    def __init__(self) -> None:
        # Paredes fijas del colegio.
        self.walls: List[Position] = [
            (0, 0), (1, 0), (7, 0), (8, 0),
            (0, 1), (8, 1),
            (0, 2), (4, 2), (8, 2),
            (0, 3), (8, 3),
            (0, 4), (8, 4),
            (0, 5), (8, 5),
            (0, 6), (1, 6), (7, 6), (8, 6),
            (3, 3), (3, 4), (3, 5), (5, 1), (5, 2), (5, 3),
        ]
        self.exit: Position = (8, 3)

    def in_bounds(self, position: Position) -> bool:
        x, y = position
        return 0 <= x < self.width and 0 <= y < self.height

    def is_walkable(self, position: Position, occupied: Iterable[Position] = ()) -> bool:
        return (
            self.in_bounds(position)
            and position not in self.walls
            and position not in occupied
        )

    def render(self, entities: Iterable[Entity]) -> str:
        board = [[" "] * self.width for _ in range(self.height)]

        for x, y in self.walls:
            board[y][x] = "#"

        ex, ey = self.exit
        board[ey][ex] = "⛩"

        for entity in entities:
            x, y = entity.position
            board[y][x] = entity.symbol

        lines = ["".join(row) for row in board]
        legend = "⛩: Salida | ✨: Shavy | 👣: Rastro | 👹: Monstruos"
        return "\n".join(lines + ["", legend])


DIRECTIONS: Dict[str, Position] = {
    "w": (0, -1),
    "a": (-1, 0),
    "s": (0, 1),
    "d": (1, 0),
}


def clamp_step(step: int) -> int:
    return (step > 0) - (step < 0)


def next_monster_move(monster: Entity, player: Entity, school: School, occupied: Iterable[Position]) -> Position:
    """Calcula el siguiente paso de un monstruo hacia el jugador."""

    mx, my = monster.position
    px, py = player.position

    preferred = []
    if px != mx:
        preferred.append((clamp_step(px - mx), 0))
    if py != my:
        preferred.append((0, clamp_step(py - my)))

    random.shuffle(preferred)
    preferred.append(random.choice([(1, 0), (-1, 0), (0, 1), (0, -1)]))

    for dx, dy in preferred:
        candidate = (mx + dx, my + dy)
        if school.is_walkable(candidate, occupied):
            return candidate
    return monster.position


def play() -> None:
    """Punto de entrada principal del juego."""

    school = School()
    shavy = Entity("Shavy", "✨", (1, 3))
    trail: List[Entity] = []
    monsters = [
        Entity("Hilario", "👹", (6, 1)),
        Entity("Luna", "👹", (6, 5)),
        Entity("Bruma", "👹", (2, 5)),
    ]

    turn = 1
    print("Bienvenido, Shavy. Encuentra la salida (⛩) antes de que los monstruos te alcancen.\n")

    while True:
        entities = [shavy, *trail, *monsters]
        print(f"Turno {turn}\n{school.render(entities)}\n")

        if shavy.position == school.exit:
            print("¡Lo lograste! Shavy escapó del colegio antes de que Hilario pudiera atraparlo.")
            break

        move = input("Movimiento (W/A/S/D): ").strip().lower()
        if move not in DIRECTIONS:
            print("Movimiento inválido. Usa W, A, S o D.\n")
            continue

        proposed = shavy.move(DIRECTIONS[move])
        if not school.is_walkable(proposed, [entity.position for entity in monsters]):
            print("No puedes moverte ahí. Hay una pared o un monstruo bloqueando.\n")
            continue

        trail.append(Entity("Rastro", "👣", shavy.position))
        if len(trail) > 3:
            trail.pop(0)

        shavy.position = proposed

        occupied_positions = {shavy.position, *(entity.position for entity in monsters)}
        for monster in monsters:
            occupied_positions.remove(monster.position)
            new_position = next_monster_move(monster, shavy, school, occupied_positions)
            occupied_positions.add(new_position)
            monster.position = new_position

        if any(monster.position == shavy.position for monster in monsters):
            culprit = next(monster for monster in monsters if monster.position == shavy.position)
            print(school.render([shavy, *trail, *monsters]))
            print(
                f"\n¡Oh no! {culprit.name} atrapó a Shavy."
                " Inténtalo de nuevo para lograr la fuga."
            )
            break

        turn += 1


if __name__ == "__main__":
    play()
