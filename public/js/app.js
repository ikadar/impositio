const stage = new Konva.Stage({
    container: 'konva-container', // id of container <div>
    width: window.innerWidth,
    height: window.innerHeight
});

const scale = 0.8;

const layer = new Konva.Layer({
    id: "baseLayer",
    scaleX: scale,
    scaleY: scale,
});
stage.add(layer);

const baseLayer = stage.findOne("#baseLayer");

const show = (sheetLayout) => {

    console.log(sheetLayout);

    baseLayer.removeChildren();

    showPressSheet(sheetLayout);
    showMaxSheet(sheetLayout);
    showMinSheet(sheetLayout);
    showCutSheet(sheetLayout);
    showTrimLines(sheetLayout);
    showlayoutArea(sheetLayout);

    stage.add(baseLayer);
}

const showPressSheet = (sheetLayout) => {

    const sheetOffset = {
        x: 150,
        y: 130
    }

    const pressSheetGroup = new Konva.Group({
        id: "pressSheetGroup",
        x: sheetOffset.x,
        y: sheetOffset.y,
    });
    baseLayer.add(pressSheetGroup);

    const pressSheetRect = new Konva.Rect({
        id: "pressSheet",
        x: 0,
        y: 0,
        width: sheetLayout.pressSheet.width,
        height: sheetLayout.pressSheet.height,
        fill: 'white',
        // stroke: 'black',
        strokeWidth: 0
    });
    pressSheetGroup.add(pressSheetRect);

    // dimension lines - START
    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: 4,
        // color: "red",
        length: sheetLayout.pressSheet.width,
        height: sheetLayout.pressSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: 5,
        y: 0,
        // color: "red",
        length: sheetLayout.pressSheet.height,
        width: sheetLayout.pressSheet.width,
    }));

    // showPressSheetGripMargin(sheetLayout);

}

const showMaxSheet = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const maxSheetGroup = new Konva.Group({
        id: "maxSheetGroup",
        x: sheetLayout.maxSheet.x,
        y: sheetLayout.maxSheet.y,
    });
    pressSheetGroup.add(maxSheetGroup);

    const maxSheetRect = new Konva.Rect({
        x: 0,
        y: 0,
        width: sheetLayout.maxSheet.width,
        height: sheetLayout.maxSheet.height,
        fill: 'orange',
        opacity: 0.2,
        // stroke: 'black',
        strokeWidth: 0,
    });
    maxSheetGroup.add(maxSheetRect);


    // dimension lines - START
    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.maxSheet.x,
        distance: 0,
        // color: "red",
        length: sheetLayout.maxSheet.width,
        height: sheetLayout.pressSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: 0,
        y: sheetLayout.maxSheet.y,
        // color: "red",
        length: sheetLayout.maxSheet.height,
        width: sheetLayout.pressSheet.width,
    }));

}

const showMinSheet = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const minSheetGroup = new Konva.Group({
        id: "minSheetGroup",
        x: sheetLayout.minSheet.x,
        y: sheetLayout.minSheet.y,
    });
    pressSheetGroup.add(minSheetGroup);

    const minSheetRect = new Konva.Rect({
        x: 0,
        y: 0,
        width: sheetLayout.minSheet.width,
        height: sheetLayout.minSheet.height,
        fill: 'blue',
        opacity: 0.2,
        // stroke: 'black',
        strokeWidth: 0
    });
    minSheetGroup.add(minSheetRect);
}

const showlayoutArea = (sheetLayout) => {
    showTiles(sheetLayout);
    showFirstTileDimensionLines(sheetLayout);
}

const showTiles = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const layoutAreaGroup = new Konva.Group({
        id: "layoutAreaGroup",
        x: sheetLayout.layoutArea.x,
        y: sheetLayout.layoutArea.y,
    });
    pressSheetGroup.add(layoutAreaGroup);

    const tiles = sheetLayout.tiles;

    for (let i in tiles) {

        let x = tiles[i].mmCutBufferPositions.x;
        let y = tiles[i].mmCutBufferPositions.y;
        let width = tiles[i].mmCutBufferPositions.width;
        let height = tiles[i].mmCutBufferPositions.height;

        const tileWithCutBuffer = new Konva.Rect({
            x: x,
            y: y,
            width: width,
            height: height,
            fill: 'orange',
            stroke: 'black',
            strokeWidth: 0.1,
            opacity: 1,
        });
        layoutAreaGroup.add(tileWithCutBuffer);

        x = tiles[i].mmPositions.x;
        y = tiles[i].mmPositions.y;
        width = tiles[i].mmPositions.width;
        height = tiles[i].mmPositions.height;

        const tile = new Konva.Rect({
            x: x,
            y: y,
            width: width,
            height: height,
            fill: 'gray',
            stroke: 'black',
            strokeWidth: 0.1,
            opacity: 1,
        });
        layoutAreaGroup.add(tile);


        // const simpleText = new Konva.Text({
        //     x: x,
        //     y: y,
        //     width: width,
        //     height: height,
        //     align: "center",
        //     verticalAlign: "middle",
        //     text: `${tiles[i].gridPosition.x};${tiles[i].gridPosition.y}`,
        //     fontSize: 12,
        //     fontFamily: 'Helvetica Neue',
        //     fill: 'white',
        //     opacity: 1,
        // });
        // baseLayer.add(simpleText);

        // console.log(sheetLayout[i]);
    }

    showlayoutAreaHorizontalDimensionLines(sheetLayout);
    showlayoutAreaVerticalDimensionLines(sheetLayout);
}

const showFirstTileDimensionLines = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    // first tile with cutBuffer - START
    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x,
        distance: 2,
        // color: "red",
        length: sheetLayout.firstTileWithCutBuffer.width,
        height: sheetLayout.pressSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: 3,
        y: sheetLayout.layoutArea.y,
        // color: "red",
        length: sheetLayout.firstTileWithCutBuffer.height,
        width: sheetLayout.pressSheet.width,
    }));
    // first tile with cutBuffer - END

    // first tile - START
    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x + sheetLayout.firstTile.x,
        distance: 3,
        // color: "red",
        length: sheetLayout.firstTile.width,
        height: sheetLayout.pressSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: 4,
        y: sheetLayout.layoutArea.y + sheetLayout.firstTile.y,
        // color: "red",
        length: sheetLayout.firstTile.height,
        width: sheetLayout.pressSheet.width,
    }));
    // first tile - END

}

const showlayoutAreaHorizontalDimensionLines = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    // used area - START
    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: 1,
        // color: "red",
        length: sheetLayout.layoutArea.x,
        height: -30 -30,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x,
        distance: 1,
        // color: "red",
        length: sheetLayout.layoutArea.width,
        height: sheetLayout.pressSheet.height,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x + sheetLayout.layoutArea.width,
        distance: 1,
        // color: "red",
        length: sheetLayout.pressSheet.width - (sheetLayout.layoutArea.x + sheetLayout.layoutArea.width),
        height: -30 -30,
    }));

}

const showlayoutAreaVerticalDimensionLines = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(verticalDimensionLine({
        distance: 2,
        y: 0,
        // color: "red",
        length: sheetLayout.layoutArea.y,
        width: sheetLayout.pressSheet.width,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: 2,
        y: sheetLayout.layoutArea.y,
        // color: "red",
        length: sheetLayout.layoutArea.height,
        width: -50 -30 ,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: 2,
        y: sheetLayout.layoutArea.y + sheetLayout.layoutArea.height,
        // color: "red",
        length: (sheetLayout.pressSheet.height) - (sheetLayout.layoutArea.y + sheetLayout.layoutArea.height),
        width: sheetLayout.pressSheet.width,
    }));

}

const showTrimLines = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalTrimLine(sheetLayout.trimLines.top));
    pressSheetGroup.add(horizontalTrimLine(sheetLayout.trimLines.bottom));
    pressSheetGroup.add(verticalTrimLine(sheetLayout.trimLines.left));
    pressSheetGroup.add(verticalTrimLine(sheetLayout.trimLines.right));
}

const showCutSheet = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const cutSheetRect = new Konva.Rect({
        id: "cutSheetRect",
        x: sheetLayout.cutSheet.x,
        y: sheetLayout.cutSheet.y,
        width: sheetLayout.cutSheet.width,
        height: sheetLayout.cutSheet.height,
        fill: "yellow",
        opacity: 0.4

    });
    pressSheetGroup.add(cutSheetRect);

    const cutSheetGripMarginRect = new Konva.Rect({
        id: "cutSheetGripMarginRect",
        x: sheetLayout.cutSheet.gripMargin.x,
        y: sheetLayout.cutSheet.gripMargin.y,
        width: sheetLayout.cutSheet.gripMargin.width,
        height: sheetLayout.cutSheet.gripMargin.height,
        fill: "red",
        opacity: 0.4

    });
    pressSheetGroup.add(cutSheetGripMarginRect);

}

// generic

const horizontalDimensionLine = (options) => {

    const dimensionLineGroup = new Konva.Group({
        x: options.x,
        y: -1 * (10 + (options.distance * 20)),
    });

    const arrow = new Konva.Arrow({
        x: 0,
        y: 0,
        points: [0, 0, options.length, 0],
        pointerLength: 10,
        pointerWidth: 6,
        pointerAtBeginning: true,
        fill: options.color || 'black',
        stroke: options.color || 'black',
        strokeWidth: 1
    });
    dimensionLineGroup.add(arrow);

    const label = new Konva.Text({
        x: 0,
        y: - 20,
        width: options.length,
        height: 20,
        align: "center",
        verticalAlign: "middle",
        text: `${options.length} mm`,
        fontSize: 12,
        fontFamily: 'Helvetica Neue',
        fill: options.color || 'black',
        opacity: 1,
        rotation: 0,
    });
    dimensionLineGroup.add(label);

    const leftLine = new Konva.Line({
        x: 0,
        y: 0,
        points: [0, 0, 0, options.height - options.y + 30],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(leftLine);

    const rightLine = new Konva.Line({
        x: options.length,
        y: 0,
        points: [0, 0, 0, options.height - options.y + 30],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(rightLine);

    return dimensionLineGroup;

}

const verticalDimensionLine = (options) => {

    const dimensionLineGroup = new Konva.Group({
        x: -1 * (10 + (options.distance * 20)),
        y: options.y,
    });

    const arrow = new Konva.Arrow({
        x: 0,
        y: 0,
        points: [0, 0, 0, options.length],
        pointerLength: 10,
        pointerWidth: 6,
        pointerAtBeginning: true,
        fill: options.color || 'black',
        stroke: options.color || 'black',
        strokeWidth: 1
    });
    dimensionLineGroup.add(arrow);

    const label = new Konva.Text({
        x: - 20,
        y: options.length,
        width: Math.max(options.length, 40),
        height: 20,
        align: "center",
        verticalAlign: "middle",
        text: `${options.length} mm`,
        fontSize: 12,
        fontFamily: 'Helvetica Neue',
        fill: options.color || 'black',
        opacity: 1,
        rotation: 270,
    });
    dimensionLineGroup.add(label);

    const topLine = new Konva.Line({
        x: 0,
        y: 0,
        points: [0, 0, options.width - options.x + 30, 0],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(topLine);

    const bottomLine = new Konva.Line({
        x: 0,
        y: options.length,
        points: [0, 0, options.width - options.x + 30, 0],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(bottomLine);

    return dimensionLineGroup;

}

const horizontalTrimLine = (options) => {

    return new Konva.Line({
        x: options.x,
        y: options.y,
        points: [-30, 0, options.length + 30, 0],
        stroke: 'black',
        strokeWidth: 1.5,
        dash: [15, 5]
    })
}

const verticalTrimLine = (options) => {
    return new Konva.Line({
        x: options.x,
        y: options.y,
        points: [0, -30, 0, options.length + 30],
        stroke: 'black',
        strokeWidth: 1.5,
        dash: [15, 5]
    })

}

const calc = (input) => {

    fetch('/test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(input)
    })
        .then(response => response.json())
        .then(data => {

            // console.log(data);
            baseLayer.removeChildren();

            const variations = document.getElementById("variations");
            variations.innerHTML = "";

            for (let i in data) {
                const variation = document.createElement("button");
                variation.innerHTML = data[i].size;
                variation.onclick = () => {
                    show(data[i]);
                }
                variations.appendChild(variation);
            }

        })
        .catch(error => {
            console.error('Error loading JSON:', error);
        });
}


// 👇 Expose it globally
window.calc = calc;